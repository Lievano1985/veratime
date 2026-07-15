<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ScheduleProfile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InactivateScheduleProfileAction
{
    public function handle(Company $company, ScheduleProfile $profile): ScheduleProfile
    {
        if ($company->status !== 'active' || $profile->company_id !== $company->id) {
            throw new InvalidArgumentException('El perfil no pertenece a la empresa activa.');
        }

        return DB::transaction(function () use ($company, $profile): ScheduleProfile {
            $lockedProfile = ScheduleProfile::query()
                ->where('company_id', $company->id)
                ->whereKey($profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasActiveOrFutureAssignments = $lockedProfile->assignments()
                ->where('company_id', $company->id)
                ->where('status', 'active')
                ->where(function ($query): void {
                    $query->whereNull('effective_to')
                        ->orWhereDate('effective_to', '>=', now()->toDateString());
                })
                ->lockForUpdate()
                ->exists();

            if ($hasActiveOrFutureAssignments) {
                throw new InvalidArgumentException('No se puede inactivar un perfil con asignaciones vigentes o futuras.');
            }

            $lockedProfile->forceFill(['status' => 'inactive'])->save();

            return $lockedProfile->refresh();
        });
    }
}
