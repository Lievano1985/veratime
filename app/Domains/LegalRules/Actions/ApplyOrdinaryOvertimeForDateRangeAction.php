<?php

namespace App\Domains\LegalRules\Actions;

use App\Domains\MandatoryRestDays\Actions\ResolveMandatoryRestDaysForDateAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\LegalParameter;
use App\Models\LegalRuleVersion;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ApplyOrdinaryOvertimeForDateRangeAction
{
    private const WEEKLY_LIMIT_RULE = 'maximum_weekly_hours';

    private const WEEKLY_DOUBLE_OVERTIME_LIMIT_RULE = 'weekly_overtime_double_limit';

    public function __construct(
        private readonly ResolveLegalRuleVersionForDateAction $rules,
        private readonly ResolveLegalParameterForDateAction $parameters,
        private readonly ResolveMandatoryRestDaysForDateAction $mandatoryRestDays,
    ) {}

    /**
     * @return array{total: int, calculated: int, pending: int}
     */
    public function handle(Company $company, string $startDate, string $endDate, ?Center $center = null, ?int $relationshipId = null): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfWeek(CarbonInterface::MONDAY);
        $end = CarbonImmutable::parse($endDate)->endOfWeek(CarbonInterface::SUNDAY);
        $requestedStart = CarbonImmutable::parse($startDate)->startOfDay();
        $requestedEnd = CarbonImmutable::parse($endDate)->endOfDay();
        $summary = [
            'total' => 0,
            'calculated' => 0,
            'pending' => 0,
        ];

        WorkDay::query()
            ->with(['activeCalculation.workDay.company'])
            ->where('company_id', $company->id)
            ->when($relationshipId !== null, fn ($query) => $query->where('employment_relationship_id', $relationshipId))
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->when($center, fn ($query) => $query->where('center_id', $center->id))
            ->whereNotNull('active_calculation_id')
            ->orderBy('employment_relationship_id')
            ->orderBy('work_date')
            ->orderBy('worker_id')
            ->get()
            ->groupBy(fn (WorkDay $workDay): string => $this->groupKey($workDay))
            ->each(function (Collection $weekWorkDays) use ($company, $requestedStart, $requestedEnd, &$summary): void {
                $weeklyOrdinary = 0;
                $weeklyOvertimeAccumulated = 0;
                $weeklyLimit = null;
                $weeklyRuleSnapshot = null;
                $weeklyDoubleOvertimeLimit = null;
                $weeklyDoubleOvertimeRuleSnapshot = null;

                foreach ($weekWorkDays as $workDay) {
                    $calculation = $workDay->activeCalculation;

                    if (! $calculation) {
                        continue;
                    }

                    if ($weeklyLimit === null) {
                        [$weeklyLimit, $weeklyRuleSnapshot] = $this->weeklyLimit($company, $workDay->work_date);
                        [$weeklyDoubleOvertimeLimit, $weeklyDoubleOvertimeRuleSnapshot] = $this->weeklyDoubleOvertimeLimit($workDay->work_date);
                    }

                    $isRequestedDate = $workDay->work_date->betweenIncluded($requestedStart, $requestedEnd);

                    if ($isRequestedDate) {
                        $summary['total']++;
                    }

                    if (! $this->isReadyForOvertime($calculation) || $weeklyLimit <= 0 || $weeklyDoubleOvertimeLimit <= 0) {
                        $this->markPending($calculation);

                        if ($isRequestedDate) {
                            $summary['pending']++;
                        }

                        continue;
                    }

                    [$dailyLimit, $dailyRuleSnapshot] = $this->dailyLimit($company, $calculation);
                    $dailyOrdinary = min($calculation->total_work_minutes, $dailyLimit);
                    $dailyOvertime = max(0, $calculation->total_work_minutes - $dailyOrdinary);
                    $isRest = in_array($workDay->day_type, ['rest', 'mandatory_rest'], true)
                        || $this->mandatoryRestDays->handle($company, $workDay->center, $workDay->work_date)->isNotEmpty();
                    $weeklyBase = $isRest ? 0 : $dailyOrdinary;
                    $weeklyExtra = $isRest ? 0 : max(0, $dailyOrdinary - max(0, $weeklyLimit - $weeklyOrdinary));
                    $ordinary = $dailyOrdinary - $weeklyExtra;
                    $overtime = $dailyOvertime + $weeklyExtra;
                    $weeklyBefore = $weeklyOrdinary;
                    [$overtimeDouble, $overtimeTriple] = $this->overtimeBands($overtime, $weeklyOvertimeAccumulated, $weeklyDoubleOvertimeLimit);

                    $weeklyOrdinary += $isRest ? 0 : $ordinary;
                    $weeklyOvertimeAccumulated += $overtime;

                    $this->applyResult(
                        $calculation,
                        ordinary: $ordinary,
                        overtime: $overtime,
                        overtimeDouble: $overtimeDouble,
                        overtimeTriple: $overtimeTriple,
                        dailyLimit: $dailyLimit,
                        weeklyLimit: $weeklyLimit,
                        dailyRuleSnapshot: $dailyRuleSnapshot,
                        weeklyRuleSnapshot: $weeklyRuleSnapshot,
                        weeklyDoubleOvertimeRuleSnapshot: $weeklyDoubleOvertimeRuleSnapshot,
                        weeklyOrdinaryBefore: $weeklyBefore,
                        weeklyDoubleOvertimeLimit: $weeklyDoubleOvertimeLimit,
                        weeklyExcess: $weeklyExtra,
                        dailyExtra: $dailyOvertime,
                        weeklyBase: $weeklyBase,
                        restMinutes: $isRest ? $calculation->total_work_minutes : 0,
                    );

                    if ($isRequestedDate) {
                        $summary['calculated']++;
                    }
                }
            });

        return $summary;
    }

    private function groupKey(WorkDay $workDay): string
    {
        $week = $workDay->work_date->copy()->startOfWeek(CarbonInterface::MONDAY)->toDateString();

        return "{$workDay->employment_relationship_id}:{$week}";
    }

    private function isReadyForOvertime(WorkDayCalculation $calculation): bool
    {
        return $calculation->status === WorkDayCalculation::STATUS_ACTIVE
            && $calculation->classification !== WorkDayCalculation::CLASSIFICATION_PENDING
            && $calculation->total_work_minutes > 0
            && (($calculation->result_snapshot['issues'] ?? []) === []);
    }

    private function markPending(WorkDayCalculation $calculation): void
    {
        $snapshot = $calculation->result_snapshot ?? [];
        unset($snapshot['ordinary_overtime']);
        $calculation->forceFill([
            'result_snapshot' => $snapshot,
            'ordinary_minutes' => 0,
            'overtime_minutes' => 0,
            'overtime_double_minutes' => 0,
            'overtime_triple_minutes' => 0,
            'explanation' => array_replace_recursive($calculation->explanation ?? [], [
                'ordinary_overtime' => 'Pendiente: la jornada requiere clasificacion y reglas legales vigentes validas antes de calcular ordinario y extra.',
            ]),
        ])->save();
    }

    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function dailyLimit(Company $company, WorkDayCalculation $calculation): array
    {
        $workDate = $calculation->workDay->work_date;
        [$parameterCode, $ruleCode, $fallback] = match ($calculation->classification) {
            WorkDayCalculation::CLASSIFICATION_NOCTURNAL => ['company_daily_limit_nocturnal_minutes', 'daily_limit_nocturnal', 420],
            WorkDayCalculation::CLASSIFICATION_MIXED => ['company_daily_limit_mixed_minutes', 'daily_limit_mixed', 450],
            default => ['company_daily_limit_diurnal_minutes', 'daily_limit_diurnal', 480],
        };

        return $this->limitFromParameterOrRule($company, $workDate, $parameterCode, $ruleCode, $fallback);
    }

    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function weeklyLimit(Company $company, CarbonInterface $workDate): array
    {
        [$legalLimit, $legalRuleSnapshot] = $this->ruleLimit($workDate, self::WEEKLY_LIMIT_RULE, 0);
        $parameter = $this->parameters->handle($company, 'company_weekly_limit_minutes', $workDate);

        if (! $parameter instanceof LegalParameter) {
            return [$legalLimit, $legalRuleSnapshot];
        }

        $companyLimit = (int) ($parameter->value['minutes'] ?? $legalLimit);

        return [
            min($companyLimit, $legalLimit),
            [
                'source' => 'company_parameter',
                'code' => 'company_weekly_limit_minutes',
                'legal_parameter_id' => $parameter->id,
                'value' => $parameter->value,
                'effective_from' => $parameter->effective_from?->toDateString(),
                'effective_to' => $parameter->effective_to?->toDateString(),
                'legal_limit' => $legalRuleSnapshot,
            ],
        ];
    }

    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function weeklyDoubleOvertimeLimit(CarbonInterface $workDate): array
    {
        return $this->ruleLimit($workDate, self::WEEKLY_DOUBLE_OVERTIME_LIMIT_RULE, 0);
    }

    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function limitFromParameterOrRule(Company $company, CarbonInterface $workDate, string $parameterCode, string $ruleCode, int $fallback): array
    {
        $parameter = $this->parameters->handle($company, $parameterCode, $workDate);

        if ($parameter instanceof LegalParameter) {
            return [
                (int) ($parameter->value['minutes'] ?? $fallback),
                [
                    'source' => 'company_parameter',
                    'code' => $parameterCode,
                    'legal_parameter_id' => $parameter->id,
                    'value' => $parameter->value,
                    'effective_from' => $parameter->effective_from?->toDateString(),
                    'effective_to' => $parameter->effective_to?->toDateString(),
                ],
            ];
        }

        return $this->ruleLimit($workDate, $ruleCode, $fallback);
    }

    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function ruleLimit(CarbonInterface $workDate, string $ruleCode, int $fallback): array
    {
        $version = $this->rules->handle($ruleCode, $workDate);

        if ($version instanceof LegalRuleVersion) {
            return [
                (int) ($version->value['minutes'] ?? $fallback),
                [
                    'source' => 'country_rule',
                    'code' => $ruleCode,
                    'legal_rule_id' => $version->legal_rule_id,
                    'legal_rule_version_id' => $version->id,
                    'version' => $version->version,
                    'value' => $version->value,
                    'source_reference' => $version->source_reference,
                ],
            ];
        }

        return [
            $fallback,
            [
                'source' => 'fallback',
                'code' => $ruleCode,
                'value' => ['minutes' => $fallback],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $dailyRuleSnapshot
     * @param  array<string, mixed>  $weeklyRuleSnapshot
     */
    /**
     * @return array{0: int, 1: int}
     */
    private function overtimeBands(int $overtime, int $weeklyOvertimeBefore, int $weeklyDoubleOvertimeLimit): array
    {
        $doubleRemaining = max(0, $weeklyDoubleOvertimeLimit - $weeklyOvertimeBefore);
        $double = min($overtime, $doubleRemaining);

        return [$double, max(0, $overtime - $double)];
    }

    private function applyResult(WorkDayCalculation $calculation, int $ordinary, int $overtime, int $overtimeDouble, int $overtimeTriple, int $dailyLimit, int $weeklyLimit, array $dailyRuleSnapshot, array $weeklyRuleSnapshot, array $weeklyDoubleOvertimeRuleSnapshot, int $weeklyOrdinaryBefore, int $weeklyDoubleOvertimeLimit, int $weeklyExcess, int $dailyExtra, int $weeklyBase, int $restMinutes): void
    {
        $resultSnapshot = $calculation->result_snapshot ?? [];

        $calculation->forceFill([
            'ordinary_minutes' => $ordinary,
            'overtime_minutes' => $overtime,
            'overtime_double_minutes' => $overtimeDouble,
            'overtime_triple_minutes' => $overtimeTriple,
            'rules_snapshot' => array_replace_recursive($calculation->rules_snapshot ?? [], [
                'ordinary_overtime' => [
                    'schema_version' => 3,
                    'daily_limit' => $dailyRuleSnapshot,
                    'weekly_limit' => $weeklyRuleSnapshot,
                    'weekly_double_overtime_limit' => $weeklyDoubleOvertimeRuleSnapshot,
                ],
            ]),
            'result_snapshot' => array_replace_recursive($resultSnapshot, [
                'ordinary_overtime' => [
                    'schema_version' => 3,
                    'overtime_basis' => 'daily_and_weekly_excluding_rest_base',
                    'daily_overtime_minutes' => $dailyExtra,
                    'weekly_overtime_minutes' => $weeklyExcess,
                    'weekly_base_minutes' => $weeklyBase,
                    'rest_work_minutes' => $restMinutes,
                    'ordinary_minutes' => $ordinary,
                    'overtime_minutes' => $overtime,
                    'overtime_double_minutes' => $overtimeDouble,
                    'overtime_triple_minutes' => $overtimeTriple,
                    'weekly_double_overtime_limit_minutes' => $weeklyDoubleOvertimeLimit,
                    'daily_limit_minutes' => $dailyLimit,
                    'weekly_limit_minutes' => $weeklyLimit,
                    'weekly_ordinary_before_minutes' => $weeklyOrdinaryBefore,
                    'weekly_ordinary_excess_minutes' => $weeklyExcess,
                ],
            ]),
            'explanation' => array_replace_recursive($calculation->explanation ?? [], [
                'ordinary_overtime' => "Jornada sin extra {$ordinary} minutos; extra por prolongación diaria {$dailyExtra} minutos y por límite semanal en días laborables {$weeklyExcess} minutos. Descanso trabajado {$restMinutes} minutos, concepto independiente sin duplicar el total. Bandas de extra: doble {$overtimeDouble}, triple {$overtimeTriple}.",
            ]),
        ])->save();
    }
}
