<?php

namespace App\Domains\Alerts\Actions;

use App\Domains\WorkDays\Actions\ProcessSingleWorkDayAction;
use App\Models\Alert;
use App\Models\AttendanceIncident;
use App\Models\Company;
use App\Models\WorkDayCalculation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ResolveScheduledAbsenceAlertAsAttendanceIncidentAction
{
    public function __construct(
        private readonly ResolveAlertAction $resolveAlert,
        private readonly ProcessSingleWorkDayAction $processWorkDay,
    ) {}

    /**
     * @param array{incident_type: string, payment_status: string, resolution: string} $data
     */
    public function handle(Company $company, Alert $alert, User $actor, array $data): AttendanceIncident
    {
        Gate::forUser($actor)->authorize('resolve', $alert);

        if ($company->status !== 'active' || $alert->company_id !== $company->id) {
            throw new InvalidArgumentException('La alerta no pertenece a la empresa activa.');
        }

        if ($alert->rule_code !== 'scheduled_absence') {
            throw new InvalidArgumentException('Solo las faltas programadas pueden enviarse a incidencias y ausencias.');
        }

        if (! in_array($alert->status, Alert::OPEN_STATUSES, true)) {
            throw new InvalidArgumentException('Solo se pueden dictaminar alertas abiertas.');
        }

        $workDay = $alert->workDay;
        if (! $workDay || $workDay->company_id !== $company->id || ! $workDay->worker || ! $workDay->employmentRelationship) {
            throw new InvalidArgumentException('La falta no tiene una jornada valida para crear la ausencia.');
        }

        $type = (string) ($data['incident_type'] ?? '');
        $paymentStatus = (string) ($data['payment_status'] ?? '');
        $resolution = trim((string) ($data['resolution'] ?? ''));

        if (! in_array($type, $this->allowedAbsenceTypes(), true)) {
            throw new InvalidArgumentException('Selecciona un tipo de ausencia valido.');
        }

        if (! in_array($paymentStatus, AttendanceIncident::paymentStatuses(), true)) {
            throw new InvalidArgumentException('Selecciona el tratamiento operativo de pago.');
        }

        if (mb_strlen($resolution) < 5) {
            throw new InvalidArgumentException('El motivo del dictamen es obligatorio.');
        }

        $date = $workDay->work_date?->toDateString();
        if (! $date) {
            throw new InvalidArgumentException('La jornada no tiene fecha valida.');
        }

        $incident = DB::transaction(function () use ($company, $alert, $actor, $workDay, $type, $paymentStatus, $resolution, $date): AttendanceIncident {
            $lockedAlert = Alert::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->findOrFail($alert->id);

            if (! in_array($lockedAlert->status, Alert::OPEN_STATUSES, true)) {
                throw new InvalidArgumentException('Solo se pueden dictaminar alertas abiertas.');
            }

            if ($this->hasOverlap($company, (int) $workDay->worker_id, $date)) {
                throw new InvalidArgumentException('El trabajador ya tiene una incidencia aprobada para esa fecha.');
            }

            $incident = AttendanceIncident::query()->create([
                'company_id' => $company->id,
                'worker_id' => $workDay->worker_id,
                'employment_relationship_id' => $workDay->employment_relationship_id,
                'start_date' => $date,
                'end_date' => $date,
                'incident_type' => $type,
                'payment_status' => $paymentStatus,
                'status' => AttendanceIncident::STATUS_APPROVED,
                'reference' => 'Generada desde dictamen de jornada',
                'notes' => $resolution,
                'created_by' => $actor->id,
                'metadata' => [
                    'schema_version' => 1,
                    'scope' => 'work_day_alert_resolution',
                    'source_alert_id' => $lockedAlert->id,
                    'source_work_day_id' => $workDay->id,
                    'payroll_calculation' => false,
                ],
            ]);

            $this->resolveAlert->handle($company, $lockedAlert, $actor, [
                'status' => Alert::STATUS_JUSTIFIED,
                'resolution' => $resolution,
            ]);

            return $incident;
        });

        $this->processWorkDay->handle(
            $company,
            $workDay->employmentRelationship,
            $date,
            actor: $actor,
            generatedByType: WorkDayCalculation::GENERATED_BY_USER,
            reason: 'Recalculo por ausencia creada desde dictamen de jornada.',
            mode: 'attendance_incident_resolution',
        );

        return $incident->refresh();
    }

    /**
     * @return list<string>
     */
    private function allowedAbsenceTypes(): array
    {
        return [
            AttendanceIncident::TYPE_VACATION,
            AttendanceIncident::TYPE_INCAPACITY,
            AttendanceIncident::TYPE_PAID_PERMISSION,
            AttendanceIncident::TYPE_UNPAID_PERMISSION,
            AttendanceIncident::TYPE_JUSTIFIED_PAID_ABSENCE,
            AttendanceIncident::TYPE_JUSTIFIED_UNPAID_ABSENCE,
            AttendanceIncident::TYPE_MATERNITY_PATERNITY,
            AttendanceIncident::TYPE_OTHER,
        ];
    }

    private function hasOverlap(Company $company, int $workerId, string $date): bool
    {
        return AttendanceIncident::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $workerId)
            ->where('status', AttendanceIncident::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }
}
