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
     * @param array{status?: string, incident_type?: string, payment_status?: string, resolution: string} $data
     */
    public function handle(Company $company, Alert $alert, User $actor, array $data): ?AttendanceIncident
    {
        Gate::forUser($actor)->authorize('resolve', $alert);

        if ($company->status !== 'active' || $alert->company_id !== $company->id) {
            throw new InvalidArgumentException('La alerta no pertenece a la empresa activa.');
        }

        if ($alert->rule_code !== 'scheduled_absence') {
            throw new InvalidArgumentException('Solo las faltas programadas pueden enviarse a incidencias y ausencias.');
        }

        $workDay = $alert->workDay;
        if (! $workDay || $workDay->company_id !== $company->id || ! $workDay->worker || ! $workDay->employmentRelationship) {
            throw new InvalidArgumentException('La falta no tiene una jornada valida para crear la ausencia.');
        }

        $status = (string) ($data['status'] ?? Alert::STATUS_JUSTIFIED);
        $resolution = trim((string) ($data['resolution'] ?? ''));

        if (! in_array($status, [Alert::STATUS_JUSTIFIED, Alert::STATUS_CORRECTED, Alert::STATUS_CLOSED], true)) {
            throw new InvalidArgumentException('Selecciona un dictamen valido.');
        }

        if (mb_strlen($resolution) < 5) {
            throw new InvalidArgumentException('El motivo del dictamen es obligatorio.');
        }

        $date = $workDay->work_date?->toDateString();
        if (! $date) {
            throw new InvalidArgumentException('La jornada no tiene fecha valida.');
        }

        $incident = DB::transaction(function () use ($company, $alert, $actor, $workDay, $data, $status, $resolution, $date): ?AttendanceIncident {
            $lockedAlert = Alert::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->findOrFail($alert->id);

            $linkedIncident = $this->linkedIncident($company, $lockedAlert);

            if ($status !== Alert::STATUS_JUSTIFIED) {
                if ($linkedIncident && $linkedIncident->status === AttendanceIncident::STATUS_APPROVED) {
                    $metadata = $linkedIncident->metadata ?: [];
                    $metadata['cancel_reason'] = $resolution;
                    $metadata['cancel_scope'] = 'scheduled_absence_alert_resolution_changed';

                    $linkedIncident->forceFill([
                        'status' => AttendanceIncident::STATUS_CANCELLED,
                        'cancelled_by' => $actor->id,
                        'cancelled_at' => CarbonImmutable::now('UTC'),
                        'metadata' => $metadata,
                    ])->save();
                }

                return null;
            }

            $type = (string) ($data['incident_type'] ?? '');
            $paymentStatus = (string) ($data['payment_status'] ?? '');

            if (! in_array($type, $this->allowedAbsenceTypes(), true)) {
                throw new InvalidArgumentException('Selecciona un tipo de ausencia valido.');
            }

            if (! in_array($paymentStatus, AttendanceIncident::paymentStatuses(), true)) {
                throw new InvalidArgumentException('Selecciona el tratamiento operativo de pago.');
            }

            if ($this->hasOverlap($company, (int) $workDay->worker_id, $date, $linkedIncident?->id)) {
                throw new InvalidArgumentException('El trabajador ya tiene una incidencia aprobada para esa fecha.');
            }

            if ($linkedIncident) {
                $metadata = $linkedIncident->metadata ?: [];
                $history = $metadata['change_history'] ?? [];
                $history[] = [
                    'previous_status' => $linkedIncident->status,
                    'previous_incident_type' => $linkedIncident->incident_type,
                    'previous_payment_status' => $linkedIncident->payment_status,
                    'previous_notes' => $linkedIncident->notes,
                    'changed_by' => $actor->id,
                    'changed_at' => CarbonImmutable::now('UTC')->toDateTimeString(),
                ];
                $metadata['change_history'] = $history;

                $linkedIncident->forceFill([
                    'incident_type' => $type,
                    'payment_status' => $paymentStatus,
                    'status' => AttendanceIncident::STATUS_APPROVED,
                    'notes' => $resolution,
                    'cancelled_by' => null,
                    'cancelled_at' => null,
                    'metadata' => $metadata,
                ])->save();

                return $linkedIncident;
            }

            return AttendanceIncident::query()->create([
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

        $currentAlert = Alert::query()
            ->where('company_id', $company->id)
            ->findOrFail($alert->id);

        $this->resolveAlert->handle($company, $currentAlert, $actor, [
            'status' => $status,
            'resolution' => $resolution,
        ]);

        return $incident?->refresh();
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

    private function hasOverlap(Company $company, int $workerId, string $date, ?int $exceptIncidentId = null): bool
    {
        return AttendanceIncident::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $workerId)
            ->where('status', AttendanceIncident::STATUS_APPROVED)
            ->when($exceptIncidentId, fn ($query) => $query->whereKeyNot($exceptIncidentId))
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    private function linkedIncident(Company $company, Alert $alert): ?AttendanceIncident
    {
        return AttendanceIncident::query()
            ->where('company_id', $company->id)
            ->where('metadata->source_alert_id', $alert->id)
            ->latest('id')
            ->first();
    }
}
