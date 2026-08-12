<?php

namespace App\Domains\AttendanceIncidents\Actions;

use App\Models\AttendanceIncident;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateAttendanceIncidentAction
{
    /**
     * @param array<string, mixed> $data
     */
    public function handle(Company $company, User $actor, array $data): AttendanceIncident
    {
        $this->assertCanManage($company, $actor);

        return DB::transaction(function () use ($company, $actor, $data): AttendanceIncident {
            $worker = Worker::query()
                ->where('company_id', $company->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->findOrFail((int) $data['worker_id']);

            $startDate = CarbonImmutable::parse($data['start_date'])->toDateString();
            $endDate = CarbonImmutable::parse($data['end_date'])->toDateString();

            if ($endDate < $startDate) {
                throw new InvalidArgumentException('La fecha final no puede ser anterior a la fecha inicial.');
            }

            $relationship = $this->relationshipForRange($company, $worker, $startDate, $endDate);
            $type = (string) $data['incident_type'];
            $paymentStatus = (string) ($data['payment_status'] ?? AttendanceIncident::PAYMENT_NOT_APPLICABLE);

            if (! in_array($type, AttendanceIncident::types(), true)) {
                throw new InvalidArgumentException('El tipo de incidencia no es valido.');
            }

            if (! in_array($paymentStatus, AttendanceIncident::paymentStatuses(), true)) {
                throw new InvalidArgumentException('El estado de pago operativo no es valido.');
            }

            if ($this->hasOverlap($company, $worker, $startDate, $endDate)) {
                throw new InvalidArgumentException('El trabajador ya tiene una incidencia aprobada en ese rango.');
            }

            $incident = AttendanceIncident::query()->create([
                'company_id' => $company->id,
                'worker_id' => $worker->id,
                'employment_relationship_id' => $relationship?->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'incident_type' => $type,
                'payment_status' => $paymentStatus,
                'status' => AttendanceIncident::STATUS_APPROVED,
                'reference' => $this->nullableText($data['reference'] ?? null),
                'notes' => $this->nullableText($data['notes'] ?? null),
                'created_by' => $actor->id,
                'metadata' => [
                    'schema_version' => 1,
                    'scope' => 'operational_attendance_incident',
                    'payroll_calculation' => false,
                ],
            ]);

            $this->markWorkDaysForRecalculation($company, $worker, $startDate, $endDate);

            return $incident;
        });
    }

    private function assertCanManage(Company $company, User $actor): void
    {
        if ($company->status !== 'active'
            || $actor->status !== 'active'
            || ! $actor->belongsToCompany($company)
            || ! in_array($actor->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            throw new InvalidArgumentException('No puedes registrar incidencias para esta empresa.');
        }
    }

    private function relationshipForRange(Company $company, Worker $worker, string $startDate, string $endDate): ?EmploymentRelationship
    {
        return EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $worker->id)
            ->where('status', 'active')
            ->whereDate('started_at', '<=', $endDate)
            ->where(function ($query) use ($startDate): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $startDate);
            })
            ->orderByDesc('started_at')
            ->first();
    }

    private function hasOverlap(Company $company, Worker $worker, string $startDate, string $endDate): bool
    {
        return AttendanceIncident::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $worker->id)
            ->where('status', AttendanceIncident::STATUS_APPROVED)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->exists();
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function markWorkDaysForRecalculation(Company $company, Worker $worker, string $startDate, string $endDate): void
    {
        WorkDay::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $worker->id)
            ->whereDate('work_date', '>=', $startDate)
            ->whereDate('work_date', '<=', $endDate)
            ->update(['updated_at' => now()]);
    }
}
