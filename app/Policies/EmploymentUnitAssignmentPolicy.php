<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\EmploymentUnitAssignment;
use App\Models\User;
use App\Support\RoleKey;

class EmploymentUnitAssignmentPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManageAssignments($user, $company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageAssignments($user, $company);
    }

    public function update(User $user, EmploymentUnitAssignment $assignment): bool
    {
        return $this->canManageAssignments($user, $assignment->company);
    }

    public function end(User $user, EmploymentUnitAssignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }

    private function canManageAssignments(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
