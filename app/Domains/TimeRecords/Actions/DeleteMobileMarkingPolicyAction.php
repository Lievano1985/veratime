<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\MobileMarkingPolicy;
use Illuminate\Database\QueryException;
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

            try {
                $policy->delete();
            } catch (QueryException $exception) {
                if (($exception->errorInfo[0] ?? null) !== '23000') {
                    throw $exception;
                }

                throw ValidationException::withMessages([
                    'policyForm' => 'Esta política tiene evidencias o autorizaciones de marcaje asociadas y debe conservarse.',
                ]);
            }
        });
    }
}
