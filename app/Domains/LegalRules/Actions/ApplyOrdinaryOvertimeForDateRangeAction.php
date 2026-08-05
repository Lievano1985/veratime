<?php

namespace App\Domains\LegalRules\Actions;

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

    public function __construct(
        private readonly ResolveLegalRuleVersionForDateAction $rules,
        private readonly ResolveLegalParameterForDateAction $parameters,
    ) {}

    /**
     * @return array{total: int, calculated: int, pending: int}
     */
    public function handle(Company $company, string $startDate, string $endDate, ?Center $center = null): array
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
                $weeklyLimit = null;
                $weeklyRuleSnapshot = null;

                foreach ($weekWorkDays as $workDay) {
                    $calculation = $workDay->activeCalculation;

                    if (! $calculation) {
                        continue;
                    }

                    if ($weeklyLimit === null) {
                        [$weeklyLimit, $weeklyRuleSnapshot] = $this->weeklyLimit($company, $workDay->work_date);
                    }

                    $isRequestedDate = $workDay->work_date->betweenIncluded($requestedStart, $requestedEnd);

                    if ($isRequestedDate) {
                        $summary['total']++;
                    }

                    if (! $this->isReadyForOvertime($calculation)) {
                        $this->markPending($calculation);

                        if ($isRequestedDate) {
                            $summary['pending']++;
                        }

                        continue;
                    }

                    [$dailyLimit, $dailyRuleSnapshot] = $this->dailyLimit($company, $calculation);
                    $dailyOrdinary = min($calculation->total_work_minutes, $dailyLimit);
                    $dailyOvertime = max(0, $calculation->total_work_minutes - $dailyOrdinary);
                    $weeklyRemaining = max(0, $weeklyLimit - $weeklyOrdinary);
                    $ordinary = min($dailyOrdinary, $weeklyRemaining);
                    $weeklyOvertime = max(0, $dailyOrdinary - $ordinary);
                    $overtime = $dailyOvertime + $weeklyOvertime;

                    $weeklyOrdinary += $ordinary;

                    $this->applyResult(
                        $calculation,
                        ordinary: $ordinary,
                        overtime: $overtime,
                        dailyLimit: $dailyLimit,
                        weeklyLimit: $weeklyLimit,
                        dailyRuleSnapshot: $dailyRuleSnapshot,
                        weeklyRuleSnapshot: $weeklyRuleSnapshot,
                        weeklyOrdinaryBefore: $weeklyOrdinary - $ordinary,
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
        $calculation->forceFill([
            'ordinary_minutes' => 0,
            'overtime_minutes' => 0,
            'explanation' => array_replace_recursive($calculation->explanation ?? [], [
                'ordinary_overtime' => 'Pendiente: la jornada requiere clasificacion legal valida antes de calcular ordinario y extra.',
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
        return $this->limitFromParameterOrRule($company, $workDate, 'company_weekly_limit_minutes', self::WEEKLY_LIMIT_RULE, 2880);
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
     * @param array<string, mixed> $dailyRuleSnapshot
     * @param array<string, mixed> $weeklyRuleSnapshot
     */
    private function applyResult(WorkDayCalculation $calculation, int $ordinary, int $overtime, int $dailyLimit, int $weeklyLimit, array $dailyRuleSnapshot, array $weeklyRuleSnapshot, int $weeklyOrdinaryBefore): void
    {
        $resultSnapshot = $calculation->result_snapshot ?? [];

        $calculation->forceFill([
            'ordinary_minutes' => $ordinary,
            'overtime_minutes' => $overtime,
            'rules_snapshot' => array_replace_recursive($calculation->rules_snapshot ?? [], [
                'ordinary_overtime' => [
                    'schema_version' => 1,
                    'daily_limit' => $dailyRuleSnapshot,
                    'weekly_limit' => $weeklyRuleSnapshot,
                ],
            ]),
            'result_snapshot' => array_replace_recursive($resultSnapshot, [
                'ordinary_overtime' => [
                    'schema_version' => 1,
                    'ordinary_minutes' => $ordinary,
                    'overtime_minutes' => $overtime,
                    'daily_limit_minutes' => $dailyLimit,
                    'weekly_limit_minutes' => $weeklyLimit,
                    'weekly_ordinary_before_minutes' => $weeklyOrdinaryBefore,
                ],
            ]),
            'explanation' => array_replace_recursive($calculation->explanation ?? [], [
                'ordinary_overtime' => "Ordinario {$ordinary} minutos y extra {$overtime} minutos.",
            ]),
        ])->save();
    }
}
