<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\MobileMarkingPolicy;
use App\Models\MobileOfflineMarkingAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeactivateMobileMarkingPolicyAction
{
    public function handle(Company $company, MobileMarkingPolicy $policy): MobileMarkingPolicy
    {
        return DB::transaction(function () use ($company, $policy): MobileMarkingPolicy {
            Company::query()->lockForUpdate()->findOrFail($company->id);
            $policy = MobileMarkingPolicy::query()->lockForUpdate()->findOrFail($policy->id);

            if ($policy->company_id !== $company->id || $policy->status !== MobileMarkingPolicy::STATUS_ACTIVE) {
                throw ValidationException::withMessages(['policyForm' => 'Solo se pueden desactivar políticas activas de la empresa actual.']);
            }

            $policy->update(['status' => MobileMarkingPolicy::STATUS_INACTIVE]);
            MobileOfflineMarkingAuthorization::query()
                ->where('mobile_marking_policy_id', $policy->id)
                ->where('status', MobileOfflineMarkingAuthorization::STATUS_ACTIVE)
                ->update(['status' => MobileOfflineMarkingAuthorization::STATUS_REVOKED, 'revoked_at' => now()]);

            return $policy->refresh();
        });
    }
}
