<?php

namespace App\Domains\LegalRules\Actions;

use App\Domains\MandatoryRestDays\Actions\ResolveMandatoryRestDaysForDateAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\MandatoryRestDay;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class ApplySpecialLegalCasesForDateRangeAction
{
    public function __construct(
        private readonly ResolveMandatoryRestDaysForDateAction $mandatoryRestDays,
    ) {}

    /**
     * @return array{total: int, calculated: int, pending: int, sunday: int, mandatory_rest: int, weekly_rest_review: int}
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
            'sunday' => 0,
            'mandatory_rest' => 0,
            'weekly_rest_review' => 0,
        ];

        WorkDay::query()
            ->with(['activeCalculation', 'center'])
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
                $workedDates = $weekWorkDays
                    ->filter(fn (WorkDay $workDay): bool => $this->isReadyForSpecialCases($workDay->activeCalculation))
                    ->map(fn (WorkDay $workDay): string => $workDay->work_date->toDateString())
                    ->unique()
                    ->values();
                $weeklyRestSnapshot = [
                    'schema_version' => 1,
                    'worked_days_in_week' => $workedDates->count(),
                    'has_weekly_rest_day' => $workedDates->count() < 7,
                    'requires_review' => $workedDates->count() >= 7,
                    'worked_dates' => $workedDates->all(),
                ];

                foreach ($weekWorkDays as $workDay) {
                    $calculation = $workDay->activeCalculation;

                    if (! $calculation) {
                        continue;
                    }

                    $isRequestedDate = $workDay->work_date->betweenIncluded($requestedStart, $requestedEnd);

                    if ($isRequestedDate) {
                        $summary['total']++;
                    }

                    if (! $this->isReadyForSpecialCases($calculation)) {
                        $this->markPending($calculation);

                        if ($isRequestedDate) {
                            $summary['pending']++;
                        }

                        continue;
                    }

                    $mandatoryRestDays = $this->mandatoryRestDays->handle($company, $workDay->center, $workDay->work_date);
                    $sundayMinutes = $workDay->work_date->isSunday() ? $calculation->total_work_minutes : 0;
                    $mandatoryRestMinutes = $mandatoryRestDays->isNotEmpty() ? $calculation->total_work_minutes : 0;

                    $this->applyResult(
                        $calculation,
                        sundayMinutes: $sundayMinutes,
                        mandatoryRestMinutes: $mandatoryRestMinutes,
                        mandatoryRestDays: $mandatoryRestDays,
                        weeklyRestSnapshot: $weeklyRestSnapshot,
                    );

                    if ($isRequestedDate) {
                        $summary['calculated']++;
                        $summary['sunday'] += $sundayMinutes > 0 ? 1 : 0;
                        $summary['mandatory_rest'] += $mandatoryRestMinutes > 0 ? 1 : 0;
                        $summary['weekly_rest_review'] += $weeklyRestSnapshot['requires_review'] ? 1 : 0;
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

    private function isReadyForSpecialCases(?WorkDayCalculation $calculation): bool
    {
        return $calculation instanceof WorkDayCalculation
            && $calculation->status === WorkDayCalculation::STATUS_ACTIVE
            && $calculation->classification !== WorkDayCalculation::CLASSIFICATION_PENDING
            && $calculation->total_work_minutes > 0
            && (($calculation->result_snapshot['issues'] ?? []) === []);
    }

    private function markPending(WorkDayCalculation $calculation): void
    {
        $calculation->forceFill([
            'sunday_minutes' => 0,
            'mandatory_rest_minutes' => 0,
            'explanation' => array_replace_recursive($calculation->explanation ?? [], [
                'special_legal_cases' => 'Pendiente: la jornada requiere calculo legal valido antes de identificar domingo o descansos.',
            ]),
        ])->save();
    }

    /**
     * @param Collection<int, MandatoryRestDay> $mandatoryRestDays
     * @param array<string, mixed> $weeklyRestSnapshot
     */
    private function applyResult(WorkDayCalculation $calculation, int $sundayMinutes, int $mandatoryRestMinutes, Collection $mandatoryRestDays, array $weeklyRestSnapshot): void
    {
        $mandatoryRestSnapshot = $mandatoryRestDays
            ->map(fn (MandatoryRestDay $restDay): array => [
                'mandatory_rest_day_id' => $restDay->id,
                'name' => $restDay->name,
                'type' => $restDay->type,
                'scope' => $restDay->scope,
                'country_code' => $restDay->country_code,
                'jurisdiction_code' => $restDay->jurisdiction_code,
                'source_reference' => $restDay->source_reference,
            ])
            ->values()
            ->all();

        $calculation->forceFill([
            'sunday_minutes' => $sundayMinutes,
            'mandatory_rest_minutes' => $mandatoryRestMinutes,
            'rules_snapshot' => array_replace_recursive($calculation->rules_snapshot ?? [], [
                'special_legal_cases' => [
                    'schema_version' => 1,
                    'sunday' => [
                        'source' => 'calendar',
                        'rule' => 'worked_minutes_on_sunday',
                    ],
                    'mandatory_rest' => [
                        'source' => 'mandatory_rest_days',
                        'matches' => $mandatoryRestSnapshot,
                    ],
                    'weekly_rest' => [
                        'source' => 'natural_week_monday_sunday',
                    ],
                ],
            ]),
            'result_snapshot' => array_replace_recursive($calculation->result_snapshot ?? [], [
                'special_legal_cases' => [
                    'schema_version' => 1,
                    'sunday_minutes' => $sundayMinutes,
                    'mandatory_rest_minutes' => $mandatoryRestMinutes,
                    'weekly_rest' => $weeklyRestSnapshot,
                ],
            ]),
            'explanation' => array_replace_recursive($calculation->explanation ?? [], [
                'special_legal_cases' => $this->explanation($sundayMinutes, $mandatoryRestMinutes, $weeklyRestSnapshot),
            ]),
        ])->save();
    }

    /**
     * @param array<string, mixed> $weeklyRestSnapshot
     */
    private function explanation(int $sundayMinutes, int $mandatoryRestMinutes, array $weeklyRestSnapshot): string
    {
        $parts = [
            "Domingo {$sundayMinutes} minutos",
            "descanso obligatorio {$mandatoryRestMinutes} minutos",
        ];

        if ($weeklyRestSnapshot['requires_review']) {
            $parts[] = 'semana sin dia de descanso detectado para revision futura';
        } else {
            $parts[] = 'descanso semanal detectado';
        }

        return implode(', ', $parts).'.';
    }
}
