<?php

namespace App\Policies;

use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\User;
use App\Support\RoleKey;

class EmploymentRelationshipPolicy
{
    public function create(User $user, Company $company, Center $center): bool
    {
        return $center->company_id === $company->id
            && app(ScopedOperationalAccess::class)->canOperateFullCenter($user, $company, $center);
    }

    public function update(User $user, EmploymentRelationship $relationship): bool
    {
        return app(ScopedOperationalAccess::class)->canOperateRelationship($user, $relationship->company, $relationship);
    }

    public function end(User $user, EmploymentRelationship $relationship): bool
    {
        return $this->update($user, $relationship);
    }

    private function canManageCompanyRelationships(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
