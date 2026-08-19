<?php

namespace App\Policies;

use App\Domains\Organization\Actions\EnsureUserCanManageWorkerAction;
use App\Domains\Organization\Actions\ResolveUserOperationalScopeAction;
use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Center;
use App\Models\OrganizationalUnit;
use App\Models\ScheduleProfile;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

class ScheduleProfilePolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManageCompanyProfiles($user, $company)
            || $this->hasScopedViewScope($user, $company);
    }

    public function view(User $user, ScheduleProfile $profile): bool
    {
        if ($profile->company?->status !== 'active' || ! $user->belongsToCompany($profile->company)) {
            return false;
        }

        if ($this->canManageCompanyProfiles($user, $profile->company)) {
            return true;
        }

        return $profile->status === 'active' && $this->hasScopedViewScope($user, $profile->company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canCreateCompanyProfiles($user, $company);
    }

    public function update(User $user, ScheduleProfile $profile): bool
    {
        return $this->canManageCompanyProfiles($user, $profile->company);
    }

    public function inactivate(User $user, ScheduleProfile $profile): bool
    {
        return $this->update($user, $profile);
    }

    public function delete(User $user, ScheduleProfile $profile): bool
    {
        return $this->update($user, $profile);
    }

    public function reactivate(User $user, ScheduleProfile $profile): bool
    {
        return $this->update($user, $profile);
    }

    public function configureRules(User $user, ScheduleProfile $profile): bool
    {
        return $this->update($user, $profile);
    }

    public function assign(User $user, Company $company, string $assignmentScope, ?EmploymentRelationship $relationship = null, ?string $date = null): bool
    {
        if ($this->canManageCompanyProfileAssignments($user, $company)) {
            return true;
        }

        if (! in_array($user->roleKeyForCompany($company), RoleKey::scopedOperators(), true)) {
            return false;
        }

        if ($assignmentScope === 'employment_relationship') {
            if (! $relationship || $relationship->company_id !== $company->id) {
                return false;
            }

            try {
                app(EnsureUserCanManageWorkerAction::class)->handle($user, $company, $relationship, $date ?? now()->toDateString());
            } catch (AuthorizationException|InvalidArgumentException) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function assignToCenter(User $user, Company $company, Center $center, ?string $date = null): bool
    {
        if ($this->canManageCompanyProfileAssignments($user, $company)) {
            return true;
        }

        return $center->company_id === $company->id
            && app(ScopedOperationalAccess::class)->canOperateFullCenter($user, $company, $center, $date);
    }

    public function assignToUnit(User $user, Company $company, OrganizationalUnit $unit, ?string $date = null): bool
    {
        if ($this->canManageCompanyProfileAssignments($user, $company)) {
            return true;
        }

        return $unit->company_id === $company->id
            && app(ScopedOperationalAccess::class)->canOperateUnit($user, $company, $unit, $date);
    }

    private function canManageCompanyProfileAssignments(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }

    private function canCreateCompanyProfiles(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), [...RoleKey::companyManagers(), ...RoleKey::scopedOperators()], true);
    }

    private function canManageCompanyProfiles(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }

    private function hasScopedViewScope(User $user, Company $company): bool
    {
        if ($company->status !== 'active'
            || $user->status !== 'active'
            || ! $user->belongsToCompany($company)
            || ! in_array($user->roleKeyForCompany($company), RoleKey::scopeAssignableRoles(), true)) {
            return false;
        }

        try {
            $scope = app(ResolveUserOperationalScopeAction::class)->handle($company, $user, now()->toDateString());
        } catch (InvalidArgumentException) {
            return false;
        }

        return $scope['center_ids'] !== [] || $scope['organizational_unit_ids'] !== [];
    }
}
