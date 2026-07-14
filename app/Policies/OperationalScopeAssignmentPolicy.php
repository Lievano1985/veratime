<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\OperationalScopeAssignment;
use App\Models\User;
use App\Support\RoleKey;

class OperationalScopeAssignmentPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManageScopes($user, $company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageScopes($user, $company);
    }

    public function update(User $user, OperationalScopeAssignment $scope): bool
    {
        return $this->canManageScopes($user, $scope->company);
    }

    public function end(User $user, OperationalScopeAssignment $scope): bool
    {
        return $this->update($user, $scope);
    }

    private function canManageScopes(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
