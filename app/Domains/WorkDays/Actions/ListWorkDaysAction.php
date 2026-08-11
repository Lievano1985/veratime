<?php

namespace App\Domains\WorkDays\Actions;

use App\Models\Company;
use App\Models\Alert;
use App\Models\WorkDay;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListWorkDaysAction
{
    /**
     * @param array{date_from?: ?string, date_to?: ?string, center_id?: ?int, status?: ?string, schedule_status?: ?string, incident_type?: ?string, incident_status?: ?string, search?: ?string} $filters
     * @return LengthAwarePaginator<int, WorkDay>
     */
    public function handle(Company $company, array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $today = CarbonImmutable::now($company->setting?->default_timezone ?: $company->timezone)->toDateString();

        return WorkDay::query()
            ->with([
                'worker',
                'center',
                'employmentRelationship',
                'scheduleBatch',
                'activeCalculation',
                'alerts' => fn ($query) => $query
                    ->with(['alertType', 'resolver'])
                    ->orderByRaw("case when status in ('new', 'in_review', 'pending_information') then 1 else 2 end")
                    ->orderBy('detected_at'),
            ])
            ->withCount([
                'alerts as open_alerts_count' => fn ($query) => $query->whereIn('status', Alert::OPEN_STATUSES),
                'alerts as resolved_alerts_count' => fn ($query) => $query->whereNotIn('status', Alert::OPEN_STATUSES),
            ])
            ->where('company_id', $company->id)
            ->whereDate('work_date', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query
                    ->whereDate('work_date', '<', $today)
                    ->orWhere(function ($todayQuery) use ($today): void {
                        $todayQuery
                            ->whereDate('work_date', $today)
                            ->where(function ($visibleTodayQuery): void {
                                $visibleTodayQuery
                                    ->whereNotNull('active_calculation_id')
                                    ->orWhere('schedule_status', WorkDay::SCHEDULE_STATUS_UNSCHEDULED)
                                    ->orWhere('day_type', '!=', 'shift')
                                    ->orWhereNull('expected_work_minutes')
                                    ->orWhere('expected_work_minutes', '<=', 0);
                            });
                    });
            })
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('work_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('work_date', '<=', $date))
            ->when($filters['center_id'] ?? null, fn ($query, $centerId) => $query->where('center_id', $centerId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['schedule_status'] ?? null, fn ($query, $status) => $query->where('schedule_status', $status))
            ->when($filters['incident_type'] ?? null, function ($query, $type): void {
                if ($type === 'with_incidents') {
                    $query->where(function ($incidentQuery): void {
                        $incidentQuery
                            ->whereHas('alerts')
                            ->orWhereNotNull('metadata->attendance_incident')
                            ->orWhereHas('activeCalculation', fn ($calculationQuery) => $calculationQuery->whereNotNull('result_snapshot->attendance_incident'))
                            ->orWhere('schedule_status', WorkDay::SCHEDULE_STATUS_UNSCHEDULED)
                            ->orWhere('status', WorkDay::STATUS_UNDER_REVIEW)
                            ->orWhere(function ($absenceQuery): void {
                                $absenceQuery
                                    ->whereNull('active_calculation_id')
                                    ->where('valid_time_event_count', 0)
                                    ->where('schedule_status', WorkDay::SCHEDULE_STATUS_SCHEDULED)
                                    ->where('day_type', 'shift')
                                    ->where('expected_work_minutes', '>', 0);
                            });
                    });

                    return;
                }

                if ($type === 'none') {
                    $query
                        ->whereDoesntHave('alerts')
                        ->whereNull('metadata->attendance_incident')
                        ->whereDoesntHave('activeCalculation', fn ($calculationQuery) => $calculationQuery->whereNotNull('result_snapshot->attendance_incident'));

                    return;
                }

                if ($type === 'unscheduled_work_day') {
                    $query->where('schedule_status', WorkDay::SCHEDULE_STATUS_UNSCHEDULED);

                    return;
                }

                $query->whereHas('alerts', fn ($alertQuery) => $alertQuery->where('rule_code', $type));
            })
            ->when($filters['incident_status'] ?? null, function ($query, $status): void {
                if ($status === 'pending') {
                    $query->whereHas('alerts', fn ($alertQuery) => $alertQuery->whereIn('status', Alert::OPEN_STATUSES));

                    return;
                }

                if ($status === 'dictated') {
                    $query->whereHas('alerts', fn ($alertQuery) => $alertQuery->whereNotIn('status', Alert::OPEN_STATUSES));

                    return;
                }

                if ($status === 'none') {
                    $query
                        ->whereDoesntHave('alerts')
                        ->whereNull('metadata->attendance_incident')
                        ->whereDoesntHave('activeCalculation', fn ($calculationQuery) => $calculationQuery->whereNotNull('result_snapshot->attendance_incident'));
                }
            })
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $term = trim((string) $search);

                if ($term === '') {
                    return;
                }

                $query->whereHas('worker', function ($workerQuery) use ($term): void {
                    $workerQuery
                        ->where('full_name', 'like', "%{$term}%")
                        ->orWhere('employee_code', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('work_date')
            ->orderBy('worker_id')
            ->paginate($perPage);
    }
}
