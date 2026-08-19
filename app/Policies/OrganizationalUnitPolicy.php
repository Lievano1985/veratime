<?php

namespace App\Policies;

use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Models\Center;
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
            && in_array($user->roleKeyForCompany($company), [...RoleKey::companyManagers(), ...RoleKey::scopeAssignableRoles()], true);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageCompanyUnits($user, $company);
    }

    public function update(User $user, OrganizationalUnit $unit): bool
    {
        return $this->canManageCompanyUnits($user, $unit->company)
            || app(ScopedOperationalAccess::class)->canOperateUnit($user, $unit->company, $unit);
    }

    public function createInScope(User $user, Company $company, Center $center, ?OrganizationalUnit $parent = null): bool
    {
        if ($this->canManageCompanyUnits($user, $company)) {
            return $center->company_id === $company->id;
        }

        if ($center->company_id !== $company->id
            || $center->status !== 'active'
            || ! in_array($user->roleKeyForCompany($company), RoleKey::scopedOperators(), true)) {
            return false;
        }

        $scope = app(ScopedOperationalAccess::class)->scope($user, $company);

        if (in_array($center->id, $scope['center_ids'], true)) {
            return true;
        }

        return $parent
            && $parent->company_id === $company->id
            && $parent->center_id === $center->id
            && in_array($parent->id, $scope['organizational_unit_ids'], true);
    }

    public function inactivate(User $user, OrganizationalUnit $unit): bool
    {
        return $this->update($user, $unit);
    }

    public function delete(User $user, OrganizationalUnit $unit): bool
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
