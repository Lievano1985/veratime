<?php

namespace App\Domains\AttendanceIncidents\Actions;

use App\Models\AttendanceIncident;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkDay;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CancelAttendanceIncidentAction
{
    public function handle(Company $company, AttendanceIncident $incident, User $actor, string $reason): AttendanceIncident
    {
        if ($company->status !== 'active'
            || $actor->status !== 'active'
            || ! $actor->belongsToCompany($company)
            || ! in_array($actor->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            throw new InvalidArgumentException('No puedes cancelar incidencias para esta empresa.');
        }

        if ($incident->company_id !== $company->id) {
            throw new InvalidArgumentException('La incidencia debe pertenecer a la empresa activa.');
        }

        $cleanReason = trim($reason);

        if (mb_strlen($cleanReason) < 5) {
            throw new InvalidArgumentException('El motivo de cancelacion es obligatorio.');
        }

        return DB::transaction(function () use ($company, $incident, $actor, $cleanReason): AttendanceIncident {
            $incident = AttendanceIncident::query()
                ->where('company_id', $company->id)
                ->whereKey($incident->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($incident->status === AttendanceIncident::STATUS_CANCELLED) {
                return $incident;
            }

            $metadata = $incident->metadata ?: [];
            $metadata['cancel_reason'] = $cleanReason;

            $incident->forceFill([
                'status' => AttendanceIncident::STATUS_CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => CarbonImmutable::now('UTC'),
                'metadata' => $metadata,
            ])->save();

            WorkDay::query()
                ->where('company_id', $company->id)
                ->where('worker_id', $incident->worker_id)
                ->whereDate('work_date', '>=', $incident->start_date)
                ->whereDate('work_date', '<=', $incident->end_date)
                ->update(['updated_at' => now()]);

            return $incident;
        });
    }
}
