<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ScheduleProfile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReactivateScheduleProfileAction
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

            if ($lockedProfile->profile_type === 'pattern' && $lockedProfile->pattern_mode === 'weekly') {
                $rules = $lockedProfile->weeklyRules()->lockForUpdate()->get();
                if ($rules->count() !== 7 || $rules->where('day_type', 'shift')->isEmpty()) {
                    throw new InvalidArgumentException('El perfil por patron semanal no tiene reglas semanales validas para reactivarse.');
                }
            }

            if ($lockedProfile->profile_type === 'pattern' && $lockedProfile->pattern_mode === 'cycle') {
                $rules = $lockedProfile->cycleRules()->lockForUpdate()->get();
                if ($rules->count() < 2 || $rules->where('day_type', 'shift')->isEmpty()) {
                    throw new InvalidArgumentException('El perfil por ciclo no tiene reglas validas para reactivarse.');
                }
            }

            if ($lockedProfile->profile_type === 'flexible') {
                $rules = $lockedProfile->flexibleRules()->lockForUpdate()->get();
                if ($rules->count() !== 7 || $rules->where('day_type', 'work')->isEmpty()) {
                    throw new InvalidArgumentException('El perfil flexible no tiene reglas validas para reactivarse.');
                }
            }

            if ($lockedProfile->profile_type === 'on_call') {
                $rules = $lockedProfile->onCallRules()->lockForUpdate()->get();
                if ($rules->count() !== 7 || $rules->where('day_type', 'on_call')->isEmpty()) {
                    throw new InvalidArgumentException('El perfil bajo demanda no tiene reglas validas para reactivarse.');
                }
            }

            $lockedProfile->forceFill(['status' => 'active'])->save();

            return $lockedProfile->refresh()->load(['weeklyRules.shiftTemplate', 'cycleRules.shiftTemplate', 'flexibleRules', 'onCallRules']);
        });
    }
}
