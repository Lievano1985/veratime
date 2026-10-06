<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\MobileMarkingPolicy;
use App\Models\MobileOfflineMarkingAuthorization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActivateMobileMarkingPolicyAction
{
    public function handle(Company $company, MobileMarkingPolicy $policy): MobileMarkingPolicy
    {
        return DB::transaction(function () use ($company, $policy): MobileMarkingPolicy {
            Company::query()->lockForUpdate()->findOrFail($company->id);
            $policy = MobileMarkingPolicy::query()->lockForUpdate()->findOrFail($policy->id);

            if ($policy->company_id !== $company->id || $policy->status !== MobileMarkingPolicy::STATUS_DRAFT) {
                throw ValidationException::withMessages(['policyForm' => 'Solo se pueden activar borradores de la empresa activa.']);
            }

            $replacedPolicyIds = MobileMarkingPolicy::query()
                ->where('company_id', $company->id)
                ->where('center_id', $policy->center_id)
                ->where('organizational_unit_id', $policy->organizational_unit_id)
                ->where('status', MobileMarkingPolicy::STATUS_ACTIVE)
                ->lockForUpdate()
                ->pluck('id');

            if ($replacedPolicyIds->isNotEmpty()) {
                MobileMarkingPolicy::query()->whereIn('id', $replacedPolicyIds)->update(['status' => MobileMarkingPolicy::STATUS_INACTIVE]);
                MobileOfflineMarkingAuthorization::query()
                    ->whereIn('mobile_marking_policy_id', $replacedPolicyIds)
                    ->where('status', MobileOfflineMarkingAuthorization::STATUS_ACTIVE)
                    ->update(['status' => MobileOfflineMarkingAuthorization::STATUS_REVOKED, 'revoked_at' => now()]);
            }

            $policy->update(['status' => MobileMarkingPolicy::STATUS_ACTIVE]);

            return $policy->refresh();
        });
    }
}
