<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\MobileMarkingPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveMobileMarkingPolicyAction
{
    public function handle(Company $company, int $policyId): void
    {
        DB::transaction(function () use ($company, $policyId): void {
            Company::query()->lockForUpdate()->findOrFail($company->id);
            $policy = MobileMarkingPolicy::query()->where('company_id', $company->id)->lockForUpdate()->findOrFail($policyId);

            if ($policy->status !== MobileMarkingPolicy::STATUS_INACTIVE) {
                throw ValidationException::withMessages(['policyForm' => 'Solo se pueden archivar políticas inactivas.']);
            }

            $policy->update(['status' => MobileMarkingPolicy::STATUS_ARCHIVED]);
        });
    }
}
