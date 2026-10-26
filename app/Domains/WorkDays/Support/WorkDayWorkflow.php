<?php

namespace App\Domains\WorkDays\Support;

use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Models\Alert;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Support\RoleKey;
use Illuminate\Database\Eloquent\Builder;

class WorkDayWorkflow
{
    public static function visibleTo(Builder $query, Company $company, User $user, string $date): void
    {
        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return;
        }
        $scope = app(ScopedOperationalAccess::class)->scope($user, $company, $date);
        $query->where(function (Builder $visible) use ($scope, $company): void {
            $visible->whereIn('center_id', $scope['center_ids'])
                ->orWhereExists(function ($units) use ($scope, $company): void {
                    $units->selectRaw('1')->from('employment_unit_assignments')
                        ->where('company_id', $company->id)
                        ->whereColumn('employment_relationship_id', 'work_days.employment_relationship_id')
                        ->whereIn('organizational_unit_id', $scope['organizational_unit_ids'])
                        ->where('status', 'active')->whereColumn('effective_from', '<=', 'work_days.work_date')
                        ->where(fn ($dates) => $dates->whereNull('effective_to')->orWhereColumn('effective_to', '>=', 'work_days.work_date'));
                });
        });
    }

    public const ATTENDANCE_RULES = ['scheduled_absence', 'late_arrival_detected', 'early_departure_detected', 'incomplete_work_day'];

    public const ADMINISTRATIVE_AUTHORIZATION_RULES = ['overtime_detected', 'scheduled_rest_work'];

    public const WEEKLY_RULES = ['weekly_hours_exceeded', 'weekly_overtime_exceeded', 'weekly_overtime_days_exceeded', 'weekly_rest_missing', 'weekly_sunday_work'];

    public static function completed(Builder $query): void
    {
        $query->whereDoesntHave('alerts', fn (Builder $alerts) => $alerts
            ->whereIn('rule_code', self::ATTENDANCE_RULES)->whereIn('status', Alert::OPEN_STATUSES))
            ->where(function (Builder $ready): void {
                $ready->whereHas('activeCalculation', fn (Builder $calculation) => $calculation
                    ->where('status', WorkDayCalculation::STATUS_ACTIVE)
                    ->where(fn (Builder $activity) => $activity->where('total_work_minutes', '>', 0)
                        ->orWhereNotNull('result_snapshot->attendance_incident'))
                    ->where(fn (Builder $issues) => $issues->whereNull('result_snapshot->issues')->orWhereJsonLength('result_snapshot->issues', 0)))
                    ->orWhere(fn (Builder $rest) => $rest->whereIn('day_type', ['rest', 'mandatory_rest'])
                        ->where('valid_time_event_count', 0)->whereNull('active_calculation_id'));
            });
    }

    public static function isCompleted(WorkDay $day): bool
    {
        return WorkDay::query()->where('company_id', $day->company_id)->whereKey($day->id)
            ->tap(fn (Builder $query) => self::completed($query))->exists();
    }

    public static function filter(Builder $query, string $state): void
    {
        if (! in_array($state, ['completed', 'pending'], true)) {
            return;
        }
        // A subquery avoids confusing "with alerts" with an incomplete attendance record.
        $completed = WorkDay::query()->select('id');
        self::completed($completed);
        $state === 'completed' ? $query->whereIn('id', $completed) : $query->whereNotIn('id', $completed);
    }
}
