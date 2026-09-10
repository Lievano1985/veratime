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
     * @param array{date_from?: ?string, date_to?: ?string, center_id?: ?int, center_ids?: ?array<int>, relationship_ids?: ?array<int>, status?: ?string, schedule_status?: ?string, incident_type?: ?string, incident_status?: ?string, situation?: ?string, attention?: ?string, search?: ?string} $filters
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
            ->when(array_key_exists('center_ids', $filters) && $filters['center_ids'] !== null, function ($query) use ($filters): void {
                $centerIds = $filters['center_ids'] ?? [];
                $relationshipIds = $filters['relationship_ids'] ?? [];

                $query->where(function ($accessQuery) use ($centerIds, $relationshipIds): void {
                    if ($centerIds !== []) {
                        $accessQuery->whereIn('center_id', $centerIds);
                    }

                    if ($relationshipIds !== []) {
                        $method = $centerIds === [] ? 'whereIn' : 'orWhereIn';
                        $accessQuery->{$method}('employment_relationship_id', $relationshipIds);
                    }

                    if ($centerIds === [] && $relationshipIds === []) {
                        $accessQuery->whereRaw('1 = 0');
                    }
                });
            })
            ->when($filters['center_id'] ?? null, fn ($query, $centerId) => $query->where('center_id', $centerId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['schedule_status'] ?? null, fn ($query, $status) => $query->where('schedule_status', $status))
            ->when($filters['situation'] ?? null, fn ($query, $situation) => $this->applySituationFilter($query, $situation))
            ->when($filters['attention'] ?? null, fn ($query, $attention) => $this->applyAttentionFilter($query, $attention))
            ->when($filters['incident_type'] ?? null, function ($query, $type): void {
                if ($type === 'with_incidents') {
                    $query->where(function ($incidentQuery): void {
                        $incidentQuery
                            ->whereHas('alerts', fn ($alertQuery) => $alertQuery->where('status', '!=', Alert::STATUS_CLOSED))
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
                        ->whereDoesntHave('alerts', fn ($alertQuery) => $alertQuery->where('status', '!=', Alert::STATUS_CLOSED))
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
                        ->whereDoesntHave('alerts', fn ($alertQuery) => $alertQuery->where('status', '!=', Alert::STATUS_CLOSED))
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

    private function applySituationFilter($query, string $situation): void
    {
        match ($situation) {
            'normal' => $this->whereWithoutVisibleIncident($query)
                ->where('status', WorkDay::STATUS_CALCULATED)
                ->where('schedule_status', WorkDay::SCHEDULE_STATUS_SCHEDULED)
                ->where('day_type', 'shift')
                ->whereHas('activeCalculation', fn ($calculationQuery) => $calculationQuery->where('total_work_minutes', '>', 0)),
            'scheduled_absence' => $this->whereScheduledAbsenceCandidate($query),
            'justified_absence' => $this->whereAttendanceIncidentType($query, [
                'justified_paid_absence',
                'justified_unpaid_absence',
            ]),
            'vacation' => $this->whereAttendanceIncidentType($query, ['vacation']),
            'incapacity' => $this->whereAttendanceIncidentType($query, ['incapacity']),
            'permission' => $this->whereAttendanceIncidentType($query, [
                'paid_permission',
                'unpaid_permission',
                'maternity_paternity',
                'other',
            ]),
            'overtime_detected',
            'late_arrival_detected',
            'early_departure_detected',
            'incomplete_work_day',
            'sunday_work',
            'mandatory_rest_work',
            'weekly_rest_missing' => $this->whereAlertRule($query, $situation),
            'rest' => $query->where('day_type', 'rest'),
            'unscheduled_work_day' => $query->where('schedule_status', WorkDay::SCHEDULE_STATUS_UNSCHEDULED),
            'without_calculation' => $query->whereNull('active_calculation_id'),
            'with_incidents' => $this->whereWithVisibleIncident($query),
            default => null,
        };
    }

    private function applyAttentionFilter($query, string $attention): void
    {
        match ($attention) {
            'requires_attention' => $query->where(function ($attentionQuery): void {
                $attentionQuery
                    ->whereHas('alerts', fn ($alertQuery) => $alertQuery->whereIn('status', Alert::OPEN_STATUSES))
                    ->orWhere('status', WorkDay::STATUS_UNDER_REVIEW)
                    ->orWhere(function ($absenceQuery): void {
                        $this->whereScheduledAbsenceCandidate($absenceQuery);
                    });
            }),
            'resolved' => $query->where(function ($attentionQuery): void {
                $attentionQuery
                    ->whereNotNull('metadata->attendance_incident')
                    ->orWhereHas('activeCalculation', fn ($calculationQuery) => $calculationQuery->whereNotNull('result_snapshot->attendance_incident'))
                    ->orWhereHas('alerts', fn ($alertQuery) => $alertQuery->whereIn('status', [
                        Alert::STATUS_JUSTIFIED,
                        Alert::STATUS_CORRECTED,
                    ]));
            }),
            'closed_not_applicable' => $query->whereHas('alerts', fn ($alertQuery) => $alertQuery
                ->where('status', Alert::STATUS_CLOSED)
                ->whereNotNull('resolved_by')
                ->where('metadata->resolution->status', Alert::STATUS_CLOSED)),
            'none' => $this->whereWithoutVisibleIncident($query),
            default => null,
        };
    }

    private function whereWithVisibleIncident($query)
    {
        return $query->where(function ($incidentQuery): void {
            $incidentQuery
                ->whereHas('alerts', fn ($alertQuery) => $alertQuery->where('status', '!=', Alert::STATUS_CLOSED))
                ->orWhereNotNull('metadata->attendance_incident')
                ->orWhereHas('activeCalculation', fn ($calculationQuery) => $calculationQuery->whereNotNull('result_snapshot->attendance_incident'))
                ->orWhere('schedule_status', WorkDay::SCHEDULE_STATUS_UNSCHEDULED)
                ->orWhere('status', WorkDay::STATUS_UNDER_REVIEW)
                ->orWhere(function ($absenceQuery): void {
                    $this->whereScheduledAbsenceCandidate($absenceQuery);
                });
        });
    }

    private function whereWithoutVisibleIncident($query)
    {
        return $query
            ->whereDoesntHave('alerts', fn ($alertQuery) => $alertQuery->where('status', '!=', Alert::STATUS_CLOSED))
            ->whereNull('metadata->attendance_incident')
            ->whereDoesntHave('activeCalculation', fn ($calculationQuery) => $calculationQuery->whereNotNull('result_snapshot->attendance_incident'))
            ->where('schedule_status', '!=', WorkDay::SCHEDULE_STATUS_UNSCHEDULED)
            ->where('status', '!=', WorkDay::STATUS_UNDER_REVIEW)
            ->where(function ($absenceQuery): void {
                $absenceQuery
                    ->whereNotNull('active_calculation_id')
                    ->orWhere('valid_time_event_count', '>', 0)
                    ->orWhere('schedule_status', '!=', WorkDay::SCHEDULE_STATUS_SCHEDULED)
                    ->orWhere('day_type', '!=', 'shift')
                    ->orWhereNull('expected_work_minutes')
                    ->orWhere('expected_work_minutes', '<=', 0);
            });
    }

    private function whereScheduledAbsenceCandidate($query)
    {
        return $query
            ->whereNull('active_calculation_id')
            ->whereDoesntHave('alerts')
            ->whereNull('metadata->attendance_incident')
            ->where('valid_time_event_count', 0)
            ->where('schedule_status', WorkDay::SCHEDULE_STATUS_SCHEDULED)
            ->where('day_type', 'shift')
            ->where('expected_work_minutes', '>', 0);
    }

    /**
     * @param list<string> $types
     */
    private function whereAttendanceIncidentType($query, array $types): void
    {
        $query->where(function ($incidentQuery) use ($types): void {
            $incidentQuery
                ->whereIn('metadata->attendance_incident->incident_type', $types)
                ->orWhereHas('activeCalculation', fn ($calculationQuery) => $calculationQuery->whereIn('result_snapshot->attendance_incident->incident_type', $types));
        });
    }

    private function whereAlertRule($query, string $ruleCode): void
    {
        $query->whereHas('alerts', fn ($alertQuery) => $alertQuery
            ->where('rule_code', $ruleCode)
            ->where('status', '!=', Alert::STATUS_CLOSED));
    }
}
