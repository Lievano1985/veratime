<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ScheduleProfile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReplaceScheduleProfileOnCallRulesAction
{
    public function __construct(private ValidateScheduleProfileOnCallRulesAction $validator)
    {
    }

    public function handle(Company $company, ScheduleProfile $profile, array $rules): ScheduleProfile
    {
        $this->assertTenant($company, $profile);
        if ($profile->profile_type !== 'on_call' || $profile->pattern_mode !== null) {
            throw new InvalidArgumentException('Solo los perfiles bajo demanda admiten reglas bajo demanda.');
        }

        $normalized = $this->validator->handle($rules);

        return DB::transaction(function () use ($company, $profile, $normalized): ScheduleProfile {
            $lockedProfile = ScheduleProfile::query()
                ->where('company_id', $company->id)
                ->whereKey($profile->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedProfile->onCallRules()->lockForUpdate()->get();
            $lockedProfile->onCallRules()->delete();

            foreach ($normalized as $rule) {
                $model = $lockedProfile->onCallRules()->make($rule);
                $model->company()->associate($company);
                $model->save();
            }

            return $lockedProfile->refresh()->load('onCallRules');
        });
    }

    private function assertTenant(Company $company, ScheduleProfile $profile): void
    {
        if ($company->status !== 'active' || $profile->company_id !== $company->id) {
            throw new InvalidArgumentException('El perfil no pertenece a la empresa activa.');
        }
    }
}
