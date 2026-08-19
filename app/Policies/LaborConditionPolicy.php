<?php

namespace App\Policies;

use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\LaborCondition;
use App\Models\User;
use App\Support\RoleKey;

class LaborConditionPolicy
{
    public function create(User $user, Company $company, EmploymentRelationship $relationship): bool
    {
        return $relationship->company_id === $company->id
            && app(ScopedOperationalAccess::class)->canOperateRelationship($user, $company, $relationship);
    }

    public function update(User $user, LaborCondition $condition): bool
    {
        return $condition->employmentRelationship
            && app(ScopedOperationalAccess::class)->canOperateRelationship($user, $condition->company, $condition->employmentRelationship);
    }

    private function canManageCompanyLaborConditions(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
