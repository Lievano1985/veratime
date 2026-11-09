<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\MobileMarkingPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteMobileMarkingPolicyAction
{
    public function handle(Company $company, int $policyId): void
    {
        DB::transaction(function () use ($company, $policyId): void {
            Company::query()->lockForUpdate()->findOrFail($company->id);
            $policy = MobileMarkingPolicy::query()->where('company_id', $company->id)->lockForUpdate()->findOrFail($policyId);

            if (! in_array($policy->status, [MobileMarkingPolicy::STATUS_INACTIVE, MobileMarkingPolicy::STATUS_ARCHIVED], true)) {
                throw ValidationException::withMessages(['policyForm' => 'Solo se pueden eliminar políticas inactivas.']);
            }

            $policy->delete();
        });
    }
}
