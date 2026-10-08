<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\MobileMarkingPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateMobileMarkingPolicyDraftVersionAction
{
    public function handle(Company $company, MobileMarkingPolicy $source): MobileMarkingPolicy
    {
        return DB::transaction(function () use ($company, $source): MobileMarkingPolicy {
            Company::query()->lockForUpdate()->findOrFail($company->id);

            $source = MobileMarkingPolicy::query()->lockForUpdate()->findOrFail($source->id);

            if ($source->company_id !== $company->id) {
                throw ValidationException::withMessages(['policyForm' => 'La política no pertenece a la empresa activa.']);
            }

            $attributes = $source->only([
                'company_id',
                'center_id',
                'organizational_unit_id',
                'mode',
                'requires_device_binding',
                'requires_biometric_unlock',
                'center_latitude',
                'center_longitude',
                'radius_meters',
                'max_accuracy_meters',
                'max_location_age_seconds',
                'offline_authorization_duration_minutes',
            ]);

            $attributes['status'] = MobileMarkingPolicy::STATUS_DRAFT;
            $attributes['version'] = (int) MobileMarkingPolicy::query()
                ->where('company_id', $company->id)
                ->where('center_id', $source->center_id)
                ->where('organizational_unit_id', $source->organizational_unit_id)
                ->lockForUpdate()
                ->max('version') + 1;

            return MobileMarkingPolicy::query()->create($attributes);
        });
    }
}
