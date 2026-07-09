<?php

namespace App\Policies;

use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\User;

class EmploymentRelationshipPolicy
{
    public function create(User $user, Company $company, Center $center): bool
    {
        return $center->company_id === $company->id
            && $this->canManageCompanyRelationships($user, $company);
    }

    public function update(User $user, EmploymentRelationship $relationship): bool
    {
        return $this->canManageCompanyRelationships($user, $relationship->company);
    }

    public function end(User $user, EmploymentRelationship $relationship): bool
    {
        return $this->update($user, $relationship);
    }

    private function canManageCompanyRelationships(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), ['owner', 'admin'], true);
    }
}
