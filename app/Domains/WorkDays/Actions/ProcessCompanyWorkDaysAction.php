<?php

namespace App\Domains\WorkDays\Actions;

use App\Domains\Alerts\Actions\EvaluateWorkDayAlertsForDateRangeAction;
use App\Domains\LegalRules\Actions\ApplyOrdinaryOvertimeForDateRangeAction;
use App\Domains\LegalRules\Actions\ApplySpecialLegalCasesForDateRangeAction;
use App\Domains\LegalRules\Actions\ClassifyWorkDayCalculationsForDateRangeAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\DailyScheduleAssignment;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessCompanyWorkDaysAction
{
    public function __construct(
        private readonly RefreshWorkDaysForDateRangeAction $refresh,
        private readonly CalculateWorkDaysForDateRangeAction $calculate,
        private readonly ClassifyWorkDayCalculationsForDateRangeAction $classify,
        private readonly ApplyOrdinaryOvertimeForDateRangeAction $ordinaryOvertime,
        private readonly ApplySpecialLegalCasesForDateRangeAction $specialLegalCases,
        private readonly EvaluateWorkDayAlertsForDateRangeAction $alerts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Company $company, ?string $startDate = null, ?string $endDate = null, ?Center $center = null, ?User $actor = null, string $mode = 'manual', string $generatedByType = WorkDayCalculation::GENERATED_BY_SYSTEM, ?string $reason = null, bool $onlyPendingOrStale = true): array
    {
        $settings = $company->setting ?: $company->setting()->create(Company::defaultSettings());
        $effectiveReason = trim((string) $reason) ?: 'Proceso operativo de jornadas';
        $range = ($startDate && $endDate)
            ? ['start_date' => CarbonImmutable::parse($startDate)->toDateString(), 'end_date' => CarbonImmutable::parse($endDate)->toDateString()]
            : $this->defaultAvailableRange($company);

        try {
            $refreshResult = $this->refresh->handle($company, $range['start_date'], $range['end_date'], $center);
            $calculationResult = $this->calculate->handle(
                $company,
                $range['start_date'],
                $range['end_date'],
                $center,
                $actor,
                $generatedByType,
                $effectiveReason,
                $onlyPendingOrStale,
            );
            $classificationResult = $this->classify->handle($company, $range['start_date'], $range['end_date'], $center);
            $ordinaryOvertimeResult = $this->ordinaryOvertime->handle($company, $range['start_date'], $range['end_date'], $center);
            $specialLegalCasesResult = $this->specialLegalCases->handle($company, $range['start_date'], $range['end_date'], $center);
            $alertsResult = $this->alerts->handle($company, $range['start_date'], $range['end_date'], $center);

            $summary = [
                'company_id' => $company->id,
                'start_date' => $range['start_date'],
                'end_date' => $range['end_date'],
                'center_id' => $center?->id,
                'actor_id' => $actor?->id,
                'mode' => $mode,
                'generated_by_type' => $generatedByType,
                'reason' => $effectiveReason,
                'status' => 'ok',
                'scheduled' => $refreshResult['scheduled'],
                'unscheduled' => $refreshResult['unscheduled'],
                'total' => $refreshResult['total'],
                'calculated' => $calculationResult['calculated'],
                'under_review' => $calculationResult['under_review'],
                'skipped' => $calculationResult['skipped'],
                'classified' => $classificationResult['classified'],
                'ordinary_overtime' => $ordinaryOvertimeResult['calculated'],
                'special_legal_cases' => $specialLegalCasesResult['calculated'],
                'alerts_created_or_updated' => $alertsResult['created_or_updated'],
                'alerts_closed' => $alertsResult['closed'],
                'alerts_open' => $alertsResult['open'],
            ];

            $this->recordResult($settings, $summary);

            return $summary;
        } catch (Throwable $exception) {
            $summary = [
                'company_id' => $company->id,
                'start_date' => $range['start_date'],
                'end_date' => $range['end_date'],
                'center_id' => $center?->id,
                'actor_id' => $actor?->id,
                'mode' => $mode,
                'generated_by_type' => $generatedByType,
                'reason' => $effectiveReason,
                'status' => 'failed',
                'scheduled' => 0,
                'unscheduled' => 0,
                'total' => 0,
                'calculated' => 0,
                'under_review' => 0,
                'skipped' => 0,
                'classified' => 0,
                'ordinary_overtime' => 0,
                'special_legal_cases' => 0,
                'alerts_created_or_updated' => 0,
                'alerts_closed' => 0,
                'alerts_open' => 0,
                'error' => $exception->getMessage(),
            ];

            $this->recordResult($settings, $summary);

            throw $exception;
        }
    }

    /**
     * @return array{start_date: string, end_date: string}
     */
    public function defaultAvailableRange(Company $company, ?CarbonImmutable $now = null): array
    {
        $timezone = $company->setting?->default_timezone ?: $company->timezone;
        $today = ($now ?: CarbonImmutable::now($timezone))->setTimezone($timezone)->startOfDay();
        $todayString = $today->toDateString();
        $dates = collect([
            DailyScheduleAssignment::query()
                ->where('daily_schedule_assignments.company_id', $company->id)
                ->whereDate('daily_schedule_assignments.work_date', '<=', $todayString)
                ->whereHas('scheduleBatch', fn ($query) => $query->where('status', 'published'))
                ->min('daily_schedule_assignments.work_date'),
            TimeEvent::query()
                ->where('company_id', $company->id)
                ->where('status', 'valid')
                ->whereNull('voided_at')
                ->whereDate('occurred_local_date', '<=', $todayString)
                ->min('occurred_local_date'),
            WorkDay::query()
                ->where('company_id', $company->id)
                ->whereDate('work_date', '<=', $todayString)
                ->where(function ($query): void {
                    $query->whereNull('active_calculation_id')
                        ->orWhere('status', WorkDay::STATUS_UNDER_REVIEW)
                        ->orWhereHas('activeCalculation', fn ($query) => $query->whereColumn('work_days.updated_at', '>', 'work_day_calculations.calculated_at'));
                })
                ->min('work_date'),
        ])->filter();

        return [
            'start_date' => $dates->isEmpty()
                ? $today->startOfWeek(CarbonInterface::MONDAY)->toDateString()
                : CarbonImmutable::parse($dates->min())->toDateString(),
            'end_date' => $todayString,
        ];
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function recordResult(CompanySetting $settings, array $summary): void
    {
        DB::transaction(function () use ($settings, $summary): void {
            $settings->refresh();
            $settings->forceFill([
                'work_days_last_refreshed_at' => CarbonImmutable::now('UTC'),
                'work_days_last_refresh_status' => $summary['status'],
                'work_days_last_refresh_summary' => $summary,
            ])->save();
        });
    }
}
