<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ScheduleProfile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReplaceScheduleProfileCycleRulesAction
{
    public function __construct(private ValidateScheduleProfileCycleRulesAction $validator)
    {
    }

    public function handle(Company $company, ScheduleProfile $profile, array $rules): ScheduleProfile
    {
        $this->assertTenant($company, $profile);
        if ($profile->profile_type !== 'pattern' || $profile->pattern_mode !== 'cycle') {
            throw new InvalidArgumentException('Solo los perfiles por ciclo admiten reglas de ciclo.');
        }

        $normalized = $this->validator->handle($company, $rules);

        return DB::transaction(function () use ($company, $profile, $normalized): ScheduleProfile {
            $lockedProfile = ScheduleProfile::query()
                ->where('company_id', $company->id)
                ->whereKey($profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedProfile->cycleRules()->lockForUpdate()->get();
            $lockedProfile->cycleRules()->delete();

            foreach ($normalized as $rule) {
                $model = $lockedProfile->cycleRules()->make($rule);
                $model->company()->associate($company);
                $model->save();
            }

            return $lockedProfile->refresh()->load('cycleRules.shiftTemplate');
        });
    }

    private function assertTenant(Company $company, ScheduleProfile $profile): void
    {
        if ($company->status !== 'active' || $profile->company_id !== $company->id) {
            throw new InvalidArgumentException('El perfil no pertenece a la empresa activa.');
        }
    }
}
