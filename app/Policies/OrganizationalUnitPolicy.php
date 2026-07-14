<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\RoleKey;

class OrganizationalUnitPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), [...RoleKey::companyManagers(), RoleKey::SUPERVISOR], true);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageCompanyUnits($user, $company);
    }

    public function update(User $user, OrganizationalUnit $unit): bool
    {
        return $this->canManageCompanyUnits($user, $unit->company);
    }

    public function inactivate(User $user, OrganizationalUnit $unit): bool
    {
        return $this->update($user, $unit);
    }

    private function canManageCompanyUnits(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
