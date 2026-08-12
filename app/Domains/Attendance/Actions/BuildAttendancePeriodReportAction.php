<?php

namespace App\Domains\Attendance\Actions;

use App\Models\Alert;
use App\Models\AttendancePeriod;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Illuminate\Support\Collection;

class BuildAttendancePeriodReportAction
{
    public function __construct(private readonly BuildAttendancePeriodWorkDayQuery $workDayQuery)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(AttendancePeriod $period): array
    {
        $period->loadMissing(['company', 'center', 'scopes.organizationalUnit']);

        $workDays = $this->workDayQuery->handle($period)
            ->with(['worker', 'center', 'dailyScheduleAssignment.organizationalUnit', 'activeCalculation', 'alerts'])
            ->orderBy('work_date')
            ->orderBy('worker_id')
            ->get();

        $workerRows = $this->workerRows($workDays);
        $summary = $this->summary($workDays, $workerRows);

        return [
            'schema_version' => 1,
            'period' => [
                'id' => $period->id,
                'company_id' => $period->company_id,
                'center_id' => $period->center_id,
                'center_name' => $period->center?->name,
                'scope_type' => $period->scope_type,
                'scope_units' => $period->scopes->map(fn ($scope) => [
                    'id' => $scope->organizational_unit_id,
                    'name' => $scope->organizationalUnit?->name,
                ])->values()->all(),
                'period_start' => $period->period_start?->toDateString(),
                'period_end' => $period->period_end?->toDateString(),
                'timezone' => $period->timezone,
            ],
            'summary' => $summary,
            'workers' => array_values($workerRows),
        ];
    }

    /**
     * @param Collection<int, WorkDay> $workDays
     * @return array<int, array<string, mixed>>
     */
    private function workerRows(Collection $workDays): array
    {
        $rows = [];

        foreach ($workDays as $workDay) {
            $workerId = (int) $workDay->worker_id;
            $calculation = $workDay->activeCalculation;

            if (! isset($rows[$workerId])) {
                $rows[$workerId] = [
                    'worker_id' => $workerId,
                    'employee_code' => $workDay->worker?->employee_code,
                    'full_name' => $workDay->worker?->full_name,
                    'center_name' => $workDay->center?->name,
                    'organizational_unit_name' => $workDay->dailyScheduleAssignment?->organizationalUnit?->name,
                    'programmed_days' => 0,
                    'attendances' => 0,
                    'absences' => 0,
                    'justified_absences' => 0,
                    'unpaid_absences' => 0,
                    'incomplete_days' => 0,
                    'ordinary_minutes' => 0,
                    'overtime_minutes' => 0,
                    'overtime_double_minutes' => 0,
                    'overtime_triple_minutes' => 0,
                    'sunday_minutes' => 0,
                    'mandatory_rest_minutes' => 0,
                    'open_incidents' => 0,
                    'closed_incidents' => 0,
                ];
            }

            $rows[$workerId]['programmed_days']++;
            $rows[$workerId]['attendances'] += $workDay->valid_time_event_count > 0 ? 1 : 0;
            $rows[$workerId]['absences'] += $this->isAbsence($workDay) ? 1 : 0;
            $rows[$workerId]['justified_absences'] += $this->hasAttendanceIncident($workDay) ? 1 : 0;
            $rows[$workerId]['unpaid_absences'] += $this->hasUnpaidAttendanceIncident($workDay) ? 1 : 0;
            $rows[$workerId]['incomplete_days'] += $workDay->status === WorkDay::STATUS_UNDER_REVIEW ? 1 : 0;
            $rows[$workerId]['open_incidents'] += $workDay->alerts->whereIn('status', Alert::OPEN_STATUSES)->count();
            $rows[$workerId]['closed_incidents'] += $workDay->alerts->whereNotIn('status', Alert::OPEN_STATUSES)->count();

            if ($calculation instanceof WorkDayCalculation) {
                $rows[$workerId]['ordinary_minutes'] += (int) $calculation->ordinary_minutes;
                $rows[$workerId]['overtime_minutes'] += (int) $calculation->overtime_minutes;
                $rows[$workerId]['overtime_double_minutes'] += (int) $calculation->overtime_double_minutes;
                $rows[$workerId]['overtime_triple_minutes'] += (int) $calculation->overtime_triple_minutes;
                $rows[$workerId]['sunday_minutes'] += (int) $calculation->sunday_minutes;
                $rows[$workerId]['mandatory_rest_minutes'] += (int) $calculation->mandatory_rest_minutes;
            }
        }

        return $rows;
    }

    /**
     * @param Collection<int, WorkDay> $workDays
     * @param array<int, array<string, mixed>> $workerRows
     * @return array<string, int>
     */
    private function summary(Collection $workDays, array $workerRows): array
    {
        return [
            'workers_included' => count($workerRows),
            'programmed_days' => $workDays->count(),
            'attendances' => array_sum(array_column($workerRows, 'attendances')),
            'absences' => array_sum(array_column($workerRows, 'absences')),
            'justified_absences' => array_sum(array_column($workerRows, 'justified_absences')),
            'unpaid_absences' => array_sum(array_column($workerRows, 'unpaid_absences')),
            'incomplete_days' => array_sum(array_column($workerRows, 'incomplete_days')),
            'ordinary_minutes' => array_sum(array_column($workerRows, 'ordinary_minutes')),
            'overtime_minutes' => array_sum(array_column($workerRows, 'overtime_minutes')),
            'overtime_double_minutes' => array_sum(array_column($workerRows, 'overtime_double_minutes')),
            'overtime_triple_minutes' => array_sum(array_column($workerRows, 'overtime_triple_minutes')),
            'sunday_minutes' => array_sum(array_column($workerRows, 'sunday_minutes')),
            'mandatory_rest_minutes' => array_sum(array_column($workerRows, 'mandatory_rest_minutes')),
            'open_incidents' => array_sum(array_column($workerRows, 'open_incidents')),
            'closed_incidents' => array_sum(array_column($workerRows, 'closed_incidents')),
        ];
    }

    private function isAbsence(WorkDay $workDay): bool
    {
        return $workDay->schedule_status === WorkDay::SCHEDULE_STATUS_SCHEDULED
            && $workDay->day_type === 'shift'
            && (int) $workDay->expected_work_minutes > 0
            && (int) $workDay->valid_time_event_count === 0
            && ! $this->hasAttendanceIncident($workDay);
    }

    private function hasAttendanceIncident(WorkDay $workDay): bool
    {
        return is_array(data_get($workDay->metadata, 'attendance_incident'))
            || is_array(data_get($workDay->activeCalculation?->result_snapshot, 'attendance_incident'));
    }

    private function hasUnpaidAttendanceIncident(WorkDay $workDay): bool
    {
        $paymentStatus = data_get($workDay->metadata, 'attendance_incident.payment_status')
            ?: data_get($workDay->activeCalculation?->result_snapshot, 'attendance_incident.payment_status');

        return $paymentStatus === 'unpaid';
    }
}
