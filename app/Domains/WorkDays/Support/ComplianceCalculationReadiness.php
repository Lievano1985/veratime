<?php

namespace App\Domains\WorkDays\Support;

use App\Models\Alert;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Illuminate\Database\Eloquent\Builder;

class ComplianceCalculationReadiness
{
    public static function isReady(WorkDay $day): bool
    {
        $calculation = $day->activeCalculation;

        return $calculation instanceof WorkDayCalculation
            && $calculation->status === WorkDayCalculation::STATUS_ACTIVE
            && $day->status !== WorkDay::STATUS_UNDER_REVIEW
            && ! $day->alerts->contains(fn (Alert $alert): bool => $alert->company_id === $day->company_id
                && in_array($alert->rule_code, WorkDayWorkflow::ATTENDANCE_RULES, true)
                && in_array($alert->status, Alert::OPEN_STATUSES, true))
            && ($calculation->result_snapshot['issues'] ?? []) === []
            && (($day->valid_time_event_count === 0
                    && $calculation->total_work_minutes === 0
                    && $calculation->ordinary_minutes === 0 && $calculation->overtime_minutes === 0
                    && data_get($calculation->result_snapshot, 'attendance_incident.status') === 'approved')
                || (in_array($calculation->classification, self::classifications(), true)
                    && ! data_get($calculation->explanation, 'legal_pending', false)
                    && $calculation->total_work_minutes > 0
                    && $calculation->ordinary_minutes + $calculation->overtime_minutes === $calculation->total_work_minutes));
    }

    public static function pending(Builder $query, int $companyId): void
    {
        $ready = WorkDay::query()->select('id')->where('company_id', $companyId)
            ->where('status', '!=', WorkDay::STATUS_UNDER_REVIEW)
            ->whereDoesntHave('alerts', fn (Builder $alerts) => $alerts->where('company_id', $companyId)
                ->whereIn('rule_code', WorkDayWorkflow::ATTENDANCE_RULES)->whereIn('status', Alert::OPEN_STATUSES))
            ->whereHas('activeCalculation', fn (Builder $calculation) => $calculation
                ->where('status', WorkDayCalculation::STATUS_ACTIVE)
                ->where(fn (Builder $issues) => $issues->whereNull('result_snapshot->issues')->orWhereJsonLength('result_snapshot->issues', 0))
                ->where(fn (Builder $state) => $state
                    ->where(fn (Builder $absence) => $absence->where('work_days.valid_time_event_count', 0)
                        ->where('total_work_minutes', 0)->where('ordinary_minutes', 0)->where('overtime_minutes', 0)
                        ->where('result_snapshot->attendance_incident->status', 'approved'))
                    ->orWhere(fn (Builder $worked) => $worked->whereIn('classification', self::classifications())
                        ->where(fn (Builder $legal) => $legal->whereNull('explanation->legal_pending')->orWhere('explanation->legal_pending', false))
                        ->where('total_work_minutes', '>', 0)->whereRaw('ordinary_minutes + overtime_minutes = total_work_minutes'))));
        $query->whereNotIn('work_days.id', $ready);
    }

    private static function classifications(): array
    {
        return [WorkDayCalculation::CLASSIFICATION_DIURNAL, WorkDayCalculation::CLASSIFICATION_NOCTURNAL, WorkDayCalculation::CLASSIFICATION_MIXED];
    }
}
