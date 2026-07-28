<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\ScheduleProfile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DeleteScheduleProfileIfUnusedAction
{
    public function handle(Company $company, ScheduleProfile $profile): void
    {
        if ($company->status !== 'active' || $profile->company_id !== $company->id) {
            throw new InvalidArgumentException('El perfil no pertenece a la empresa activa.');
        }

        DB::transaction(function () use ($company, $profile): void {
            $lockedProfile = ScheduleProfile::query()
                ->where('company_id', $company->id)
                ->whereKey($profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedProfile->assignments()->exists()
                || $this->hasGeneratedDailySchedules($company, $lockedProfile)) {
                throw new InvalidArgumentException('No se puede eliminar el perfil porque ya tiene asignaciones u horarios generados. Puedes inactivarlo.');
            }

            $lockedProfile->weeklyRules()->delete();
            $lockedProfile->cycleRules()->delete();
            $lockedProfile->flexibleRules()->delete();
            $lockedProfile->onCallRules()->delete();
            $lockedProfile->delete();
        });
    }

    private function hasGeneratedDailySchedules(Company $company, ScheduleProfile $profile): bool
    {
        return DailyScheduleAssignment::query()
            ->where('company_id', $company->id)
            ->where('source_type', 'profile')
            ->where('source_reference->schedule_profile_id', $profile->id)
            ->exists();
    }
}
