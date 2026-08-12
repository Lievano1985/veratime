<?php

namespace App\Domains\Attendance\Actions;

use App\Models\Alert;
use App\Models\AttendancePeriod;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkDay;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ValidateAttendancePeriodForClosingAction
{
    public function __construct(private readonly BuildAttendancePeriodWorkDayQuery $workDayQuery)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Company $company, AttendancePeriod $period, User $actor): array
    {
        return DB::transaction(function () use ($company, $period, $actor): array {
            $period = AttendancePeriod::query()
                ->where('company_id', $company->id)
                ->whereKey($period->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($period->status, [AttendancePeriod::STATUS_OPEN, AttendancePeriod::STATUS_READY], true)) {
                throw new InvalidArgumentException('Solo se pueden validar periodos abiertos o listos.');
            }

            $baseQuery = $this->workDayQuery->handle($period);

            $openAlerts = (clone $baseQuery)
                ->whereHas('alerts', fn ($query) => $query->whereIn('status', Alert::OPEN_STATUSES))
                ->count();

            $unresolvedWorkDays = (clone $baseQuery)
                ->whereIn('status', [WorkDay::STATUS_PENDING, WorkDay::STATUS_UNDER_REVIEW])
                ->where(function ($query): void {
                    $query
                        ->where(function ($shiftQuery): void {
                            $shiftQuery
                                ->where('schedule_status', WorkDay::SCHEDULE_STATUS_SCHEDULED)
                                ->where('day_type', 'shift')
                                ->where('expected_work_minutes', '>', 0);
                        })
                        ->orWhere('status', WorkDay::STATUS_UNDER_REVIEW);
                })
                ->whereDoesntHave('alerts', fn ($query) => $query->whereIn('status', Alert::OPEN_STATUSES))
                ->whereDoesntHave('alerts', fn ($query) => $query->whereNotIn('status', Alert::OPEN_STATUSES))
                ->count();

            $summary = [
                'schema_version' => 1,
                'validated_at' => now()->toISOString(),
                'validated_by' => $actor->id,
                'work_days' => (clone $baseQuery)->count(),
                'blockers' => [
                    'open_alerts' => $openAlerts,
                    'unresolved_work_days' => $unresolvedWorkDays,
                    'total' => $openAlerts + $unresolvedWorkDays,
                ],
                'ready_to_close' => ($openAlerts + $unresolvedWorkDays) === 0,
                'work_days_url' => route('work-days.index', [
                    'from' => $period->period_start?->toDateString(),
                    'to' => $period->period_end?->toDateString(),
                    'center' => $period->center_id,
                    'incident' => 'with_incidents',
                ], false),
            ];

            $period->forceFill([
                'status' => $summary['ready_to_close'] ? AttendancePeriod::STATUS_READY : AttendancePeriod::STATUS_OPEN,
                'validated_by' => $actor->id,
                'validated_at' => now(),
                'validation_summary' => $summary,
            ])->save();

            return $summary;
        });
    }
}
