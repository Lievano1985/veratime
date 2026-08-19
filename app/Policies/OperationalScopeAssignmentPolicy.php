<?php

namespace App\Policies;

use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Models\Company;
use App\Models\OperationalScopeAssignment;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Support\RoleKey;

class OperationalScopeAssignmentPolicy
{
    public function __construct(private ScopedOperationalAccess $scopedAccess)
    {
    }

    public function viewAny(User $user, Company $company): bool
    {
        return $this->canViewScopes($user, $company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageCompanyScopes($user, $company);
    }

    public function update(User $user, OperationalScopeAssignment $scope): bool
    {
        return $this->canManageScope($user, $scope);
    }

    public function end(User $user, OperationalScopeAssignment $scope): bool
    {
        return $this->update($user, $scope);
    }

    public function delete(User $user, OperationalScopeAssignment $scope): bool
    {
        return $this->update($user, $scope);
    }

    private function canViewScopes(User $user, Company $company): bool
    {
        if ($this->canManageCompanyScopes($user, $company)) {
            return true;
        }

        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && $user->roleKeyForCompany($company) === RoleKey::RH_OPERATIVO
            && $this->scopedAccess->canOperateCompany($user, $company);
    }

    private function canManageCompanyScopes(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }

    private function canManageScope(User $user, OperationalScopeAssignment $scope): bool
    {
        if ($this->canManageCompanyScopes($user, $scope->company)) {
            return true;
        }

        if (! $this->canViewScopes($user, $scope->company)) {
            return false;
        }

        if ($scope->user?->roleKeyForCompany($scope->company) !== RoleKey::SUPERVISOR) {
            return false;
        }

        if ($scope->center_id) {
            return $this->scopedAccess->canOperateFullCenter($user, $scope->company, $scope->center);
        }

        if ($scope->organizational_unit_id) {
            $unit = $scope->organizationalUnit instanceof OrganizationalUnit
                ? $scope->organizationalUnit
                : $scope->organizationalUnit()->first();

            return $unit !== null
                && $this->scopedAccess->canOperateFullCenter($user, $scope->company, $unit->center_id);
        }

        return false;
    }
}
