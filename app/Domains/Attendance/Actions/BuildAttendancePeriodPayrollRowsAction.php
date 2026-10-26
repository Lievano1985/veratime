<?php

namespace App\Domains\Attendance\Actions;

use App\Models\Alert;
use App\Models\AttendanceIncident;
use App\Models\AttendancePeriod;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Illuminate\Validation\ValidationException;

class BuildAttendancePeriodPayrollRowsAction
{
    public function __construct(private readonly BuildAttendancePeriodWorkDayQuery $workDayQuery) {}

    /** @return list<array<string, string|int>> */
    public function handle(AttendancePeriod $period): array
    {
        if (! in_array($period->status, [AttendancePeriod::STATUS_OPEN, AttendancePeriod::STATUS_READY], true)) {
            throw ValidationException::withMessages(['payroll_export' => 'El detalle histórico se captura al cerrar un periodo abierto, no se reconstruye después.']);
        }
        $period->loadMissing(['company', 'center']);
        $rows = [];
        $this->workDayQuery->handle($period)
            ->with(['company', 'worker', 'center', 'employmentRelationship', 'dailyScheduleAssignment.organizationalUnit', 'activeCalculation', 'alerts'])
            ->orderBy('work_date')->orderBy('worker_id')
            ->chunk(200, function ($workDays) use ($period, &$rows): void {
                foreach ($workDays as $workDay) {
                    $values = $this->row($period, $workDay);
                    foreach ($values as $value) {
                        if (! is_string($value) && ! is_int($value)) {
                            throw ValidationException::withMessages(['payroll_export' => 'Hay un dato de trabajador o jornada inválido para el cierre. Revisa los datos antes de cerrar.']);
                        }
                    }
                    $rows[] = array_combine(array_column(ExportAttendancePeriodPayrollCsvAction::defaultColumns(), 'key'), $values);
                }
            });

        return $rows;
    }

    private function row(AttendancePeriod $period, WorkDay $workDay): array
    {
        $calculation = $workDay->activeCalculation;
        $incident = $this->incidentSnapshot($workDay);
        $primaryAlert = $this->primaryAlert($workDay);
        $paidBreakMinutes = (int) ($calculation?->paid_break_minutes ?? 0);
        $breakMinutes = (int) ($calculation?->break_minutes ?? 0);
        $sundayMinutes = (int) ($calculation?->sunday_minutes ?? 0);
        $mandatoryRestMinutes = (int) ($calculation?->mandatory_rest_minutes ?? 0);
        $scheduledRest = $workDay->day_type === 'rest';
        $scheduledRestWorked = $scheduledRest && (int) ($calculation?->total_work_minutes ?? 0) > 0;
        $sundayRestWorked = $scheduledRestWorked && ($workDay->work_date?->isSunday() ?? false);

        return [
            $period->company?->name ?? '',
            $workDay->center?->name ?? '',
            $workDay->dailyScheduleAssignment?->organizationalUnit?->name ?? '',
            (int) $workDay->worker_id,
            $workDay->worker?->employee_code ?? '',
            $workDay->worker?->full_name ?? '',
            $workDay->worker?->rfc ?? '',
            $workDay->worker?->curp ?? '',
            data_get($workDay->worker?->metadata, 'nss', '') ?? '',
            $workDay->work_date?->toDateString() ?? '',
            $this->dayTypeLabel((string) $workDay->day_type),
            $this->workDayStatusLabel((string) $workDay->status),
            $this->localTime($workDay, 'first_event_at_utc'),
            $this->localTime($workDay, 'last_event_at_utc'),
            $this->hours((int) ($calculation?->ordinary_minutes ?? 0)),
            $this->hours((int) ($calculation?->overtime_minutes ?? 0)),
            $this->hours((int) ($calculation?->overtime_double_minutes ?? 0)),
            $this->hours((int) ($calculation?->overtime_triple_minutes ?? 0)),
            $this->hours((int) ($calculation?->night_minutes ?? 0)),
            $this->yesNo($sundayMinutes > 0),
            $this->hours($sundayMinutes),
            $this->yesNo($sundayMinutes > 0),
            $this->yesNo((bool) data_get($calculation?->result_snapshot, 'special_legal_cases.scheduled_rest.programmed', $scheduledRest)),
            $this->yesNo((bool) data_get($calculation?->result_snapshot, 'special_legal_cases.scheduled_rest.worked', $scheduledRestWorked)),
            $this->yesNo((bool) data_get($calculation?->result_snapshot, 'special_legal_cases.scheduled_rest.sunday_worked', $sundayRestWorked)),
            $this->yesNo((bool) data_get($calculation?->result_snapshot, 'special_legal_cases.scheduled_rest.worked', $scheduledRestWorked)),
            $this->yesNo($mandatoryRestMinutes > 0),
            $this->hours($mandatoryRestMinutes),
            $this->hours($mandatoryRestMinutes),
            $this->hours($mandatoryRestMinutes),
            $this->mandatoryRestReference($calculation),
            $this->hours($paidBreakMinutes),
            $this->hours(max(0, $breakMinutes - $paidBreakMinutes)),
            (int) ($calculation?->late_arrival_minutes ?? 0),
            $this->alertResolutionLabel($this->alertForRule($workDay, 'late_arrival_detected')),
            (int) ($calculation?->early_departure_minutes ?? 0),
            $this->alertResolutionLabel($this->alertForRule($workDay, 'early_departure_detected')),
            $this->absenceMinutes($workDay, $incident),
            $this->yesNo($incident !== [] || $primaryAlert instanceof Alert),
            $incident['incident_type'] ?? $primaryAlert?->rule_code ?? '',
            $incident['status'] ?? $primaryAlert?->status ?? '',
            $this->yesNo(($incident['payment_status'] ?? null) === AttendanceIncident::PAYMENT_PAID),
            $this->yesNo(($incident['payment_status'] ?? null) === AttendanceIncident::PAYMENT_UNPAID),
            $this->observations($incident, $primaryAlert),
        ];
    }

    private function mandatoryRestReference(?WorkDayCalculation $calculation): string
    {
        $matches = data_get($calculation?->rules_snapshot, 'special_legal_cases.mandatory_rest.matches', []);

        if (! is_array($matches) || $matches === []) {
            return '';
        }

        return collect($matches)
            ->map(function (array $match): string {
                $name = trim((string) ($match['name'] ?? ''));
                $reference = trim((string) ($match['source_reference'] ?? ''));

                return trim($name.($reference !== '' ? ' - '.$reference : ''));
            })
            ->filter()
            ->join(' | ');
    }

    /**
     * @return array<string, mixed>
     */
    private function incidentSnapshot(WorkDay $workDay): array
    {
        $snapshot = data_get($workDay->metadata, 'attendance_incident')
            ?: data_get($workDay->activeCalculation?->result_snapshot, 'attendance_incident');

        return is_array($snapshot) ? $snapshot : [];
    }

    private function primaryAlert(WorkDay $workDay): ?Alert
    {
        return $workDay->alerts
            ->sortByDesc(fn (Alert $alert): int => in_array($alert->status, Alert::OPEN_STATUSES, true) ? 1 : 0)
            ->first();
    }

    private function alertForRule(WorkDay $workDay, string $ruleCode): ?Alert
    {
        return $workDay->alerts
            ->where('rule_code', $ruleCode)
            ->sortByDesc(fn (Alert $alert): int => in_array($alert->status, Alert::OPEN_STATUSES, true) ? 1 : 0)
            ->first();
    }

    private function alertResolutionLabel(?Alert $alert): string
    {
        if (! $alert instanceof Alert) {
            return '';
        }

        return match ($alert->status) {
            Alert::STATUS_JUSTIFIED => 'justificada',
            Alert::STATUS_CORRECTED => 'confirmada',
            Alert::STATUS_CLOSED => 'no_procede',
            'new', 'in_review', 'pending_information' => 'pendiente',
            default => $alert->status,
        };
    }

    /**
     * @param  array<string, mixed>  $incident
     */
    private function absenceMinutes(WorkDay $workDay, array $incident): int
    {
        if (($incident['payment_status'] ?? null) === AttendanceIncident::PAYMENT_UNPAID) {
            return (int) $workDay->expected_work_minutes;
        }

        if ($incident !== []) {
            return 0;
        }

        if (
            $workDay->schedule_status === WorkDay::SCHEDULE_STATUS_SCHEDULED
            && $workDay->day_type === 'shift'
            && (int) $workDay->expected_work_minutes > 0
            && (int) $workDay->valid_time_event_count === 0
        ) {
            return (int) $workDay->expected_work_minutes;
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $incident
     */
    private function observations(array $incident, ?Alert $alert): string
    {
        $parts = [];

        if (($incident['reference'] ?? null) !== null) {
            $parts[] = 'Referencia: '.$incident['reference'];
        }

        if ($alert instanceof Alert) {
            $parts[] = trim($alert->title.' '.$alert->resolution);
        }

        return trim(implode(' | ', array_filter($parts)));
    }

    private function localTime(WorkDay $workDay, string $field): string
    {
        $value = $workDay->{$field};

        return $value ? $value->setTimezone($workDay->timezone ?: 'UTC')->format('H:i:s') : '';
    }

    private function hours(int $minutes): string
    {
        return number_format($minutes / 60, 2, '.', '');
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'si' : 'no';
    }

    private function dayTypeLabel(string $dayType): string
    {
        return match ($dayType) {
            'shift' => 'turno',
            'rest' => 'descanso',
            'flexible' => 'flexible',
            'on_call' => 'guardia',
            'unassigned' => 'pendiente',
            default => $dayType,
        };
    }

    private function workDayStatusLabel(string $status): string
    {
        return match ($status) {
            WorkDay::STATUS_PENDING => 'pendiente',
            WorkDay::STATUS_CALCULATED => 'calculada',
            WorkDay::STATUS_WITH_ALERTS => 'con_incidencias',
            WorkDay::STATUS_UNDER_REVIEW => 'en_revision',
            default => $status,
        };
    }
}
