<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\LaborCondition;
use App\Models\User;

class LaborConditionPolicy
{
    public function create(User $user, Company $company, EmploymentRelationship $relationship): bool
    {
        return $relationship->company_id === $company->id
            && $this->canManageCompanyLaborConditions($user, $company);
    }

    public function update(User $user, LaborCondition $condition): bool
    {
        return $this->canManageCompanyLaborConditions($user, $condition->company);
    }

    private function canManageCompanyLaborConditions(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), ['owner', 'admin'], true);
    }
}
