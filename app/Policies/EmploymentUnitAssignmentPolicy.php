<?php

namespace App\Policies;

use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\RoleKey;

class EmploymentUnitAssignmentPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManageAssignments($user, $company)
            || app(ScopedOperationalAccess::class)->canOperateCompany($user, $company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageAssignments($user, $company);
    }

    public function update(User $user, EmploymentUnitAssignment $assignment): bool
    {
        return $this->canManageAssignments($user, $assignment->company)
            || ($assignment->employmentRelationship
                && $assignment->organizationalUnit
                && $this->assignToUnit($user, $assignment->company, $assignment->employmentRelationship, $assignment->organizationalUnit));
    }

    public function assignToUnit(User $user, Company $company, EmploymentRelationship $relationship, OrganizationalUnit $unit): bool
    {
        return $relationship->company_id === $company->id
            && $unit->company_id === $company->id
            && app(ScopedOperationalAccess::class)->canOperateRelationship($user, $company, $relationship)
            && app(ScopedOperationalAccess::class)->canOperateUnit($user, $company, $unit);
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
