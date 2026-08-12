<?php

namespace App\Domains\Attendance\Actions;

use App\Models\Alert;
use App\Models\AttendanceIncident;
use App\Models\AttendancePeriod;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportAttendancePeriodPayrollCsvAction
{
    public function __construct(private readonly BuildAttendancePeriodWorkDayQuery $workDayQuery)
    {
    }

    public function handle(AttendancePeriod $period): StreamedResponse
    {
        if ($period->status !== AttendancePeriod::STATUS_CLOSED) {
            throw new \InvalidArgumentException('Solo se pueden exportar periodos cerrados.');
        }

        $period->loadMissing(['company', 'center']);
        $filename = sprintf(
            'vera-time-asistencia-%s-%s-%s.csv',
            $this->slug((string) $period->center?->name),
            $period->period_start?->format('Ymd'),
            $period->period_end?->format('Ymd'),
        );

        return response()->streamDownload(function () use ($period): void {
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'wb');
            fputcsv($output, $this->headers());

            $this->workDayQuery->handle($period)
                ->with([
                    'company',
                    'worker',
                    'center',
                    'employmentRelationship',
                    'dailyScheduleAssignment.organizationalUnit',
                    'activeCalculation',
                    'alerts',
                ])
                ->orderBy('work_date')
                ->orderBy('worker_id')
                ->chunk(200, function ($workDays) use ($output, $period): void {
                    foreach ($workDays as $workDay) {
                        fputcsv($output, $this->row($period, $workDay));
                    }
                });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @return list<string>
     */
    private function headers(): array
    {
        return [
            'empresa',
            'centro',
            'unidad',
            'empleado_id',
            'numero_empleado',
            'nombre_empleado',
            'rfc',
            'curp',
            'nss',
            'fecha',
            'tipo_dia',
            'estatus_jornada',
            'hora_entrada',
            'hora_salida',
            'horas_ordinarias',
            'horas_extra_totales',
            'horas_extra_dobles',
            'horas_extra_triples',
            'horas_nocturnas',
            'domingo_trabajado',
            'horas_domingo_trabajado',
            'prima_dominical_aplica',
            'descanso_semanal_trabajado',
            'descanso_obligatorio_trabajado',
            'horas_festivo_trabajado',
            'horas_festivo_pago_normal',
            'horas_festivo_pago_doble_adicional',
            'festivo_referencia',
            'horas_descanso_pagado',
            'horas_descanso_no_pagado',
            'minutos_retardo',
            'minutos_salida_anticipada',
            'minutos_ausencia',
            'tiene_incidencia',
            'tipo_incidencia',
            'estatus_incidencia',
            'incidencia_pagada',
            'incidencia_no_pagada',
            'observaciones',
        ];
    }

    /**
     * @return list<string|int>
     */
    private function row(AttendancePeriod $period, WorkDay $workDay): array
    {
        $calculation = $workDay->activeCalculation;
        $incident = $this->incidentSnapshot($workDay);
        $primaryAlert = $this->primaryAlert($workDay);
        $paidBreakMinutes = (int) ($calculation?->paid_break_minutes ?? 0);
        $breakMinutes = (int) ($calculation?->break_minutes ?? 0);
        $sundayMinutes = (int) ($calculation?->sunday_minutes ?? 0);
        $mandatoryRestMinutes = (int) ($calculation?->mandatory_rest_minutes ?? 0);

        return $this->sanitizeRow([
            $period->company?->name ?? '',
            $workDay->center?->name ?? '',
            $workDay->dailyScheduleAssignment?->organizationalUnit?->name ?? '',
            (int) $workDay->worker_id,
            $workDay->worker?->employee_code ?? '',
            $workDay->worker?->full_name ?? '',
            $workDay->worker?->rfc ?? '',
            $workDay->worker?->curp ?? '',
            data_get($workDay->worker?->metadata, 'nss', ''),
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
            $this->yesNo(data_get($calculation?->result_snapshot, 'special_legal_cases.weekly_rest.worked', false)),
            $this->yesNo($mandatoryRestMinutes > 0),
            $this->hours($mandatoryRestMinutes),
            $this->hours($mandatoryRestMinutes),
            $this->hours($mandatoryRestMinutes),
            $this->mandatoryRestReference($calculation),
            $this->hours($paidBreakMinutes),
            $this->hours(max(0, $breakMinutes - $paidBreakMinutes)),
            0,
            0,
            $this->absenceMinutes($workDay, $incident),
            $this->yesNo($incident !== [] || $primaryAlert instanceof Alert),
            $incident['incident_type'] ?? $primaryAlert?->rule_code ?? '',
            $incident['status'] ?? $primaryAlert?->status ?? '',
            $this->yesNo(($incident['payment_status'] ?? null) === AttendanceIncident::PAYMENT_PAID),
            $this->yesNo(($incident['payment_status'] ?? null) === AttendanceIncident::PAYMENT_UNPAID),
            $this->observations($incident, $primaryAlert),
        ]);
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

    /**
     * @param array<string, mixed> $incident
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
     * @param array<string, mixed> $incident
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

    /**
     * @param list<string|int> $row
     * @return list<string|int>
     */
    private function sanitizeRow(array $row): array
    {
        return array_map(function (string|int $value): string|int {
            if (! is_string($value) || $value === '') {
                return $value;
            }

            return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
        }, $row);
    }

    private function slug(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower($value));

        return trim((string) $slug, '-') ?: 'periodo';
    }
}
