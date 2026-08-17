<?php

namespace App\Policies;

use App\Domains\Organization\Actions\ResolveUserOperationalScopeAction;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\User;
use App\Support\RoleKey;
use InvalidArgumentException;

class DailyScheduleAssignmentPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        if ($company->status !== 'active' || $user->status !== 'active' || ! $user->belongsToCompany($company)) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return true;
        }

        if (! in_array($user->roleKeyForCompany($company), RoleKey::scopeAssignableRoles(), true)) {
            return false;
        }

        try {
            $scope = app(ResolveUserOperationalScopeAction::class)->handle($company, $user, now()->toDateString());
        } catch (InvalidArgumentException) {
            return false;
        }

        return $scope['center_ids'] !== [] || $scope['organizational_unit_ids'] !== [];
    }

    public function view(User $user, DailyScheduleAssignment $assignment): bool
    {
        $company = $assignment->company;
        if (! $company || $company->status !== 'active' || $user->status !== 'active' || ! $user->belongsToCompany($company)) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return true;
        }

        if (! in_array($user->roleKeyForCompany($company), RoleKey::scopeAssignableRoles(), true)) {
            return false;
        }

        try {
            $scope = app(ResolveUserOperationalScopeAction::class)->handle($company, $user, $assignment->work_date->toDateString());
        } catch (InvalidArgumentException) {
            return false;
        }

        if (in_array($assignment->employmentRelationship?->center_id, $scope['center_ids'], true)) {
            return true;
        }

        $unitId = $assignment->organizational_unit_id;

        return $unitId !== null && in_array($unitId, $scope['organizational_unit_ids'], true);
    }

    public function create(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }

    public function update(User $user, DailyScheduleAssignment $assignment): bool
    {
        return $assignment->scheduleBatch?->status === 'draft'
            && $this->create($user, $assignment->company);
    }

    public function deleteDraft(User $user, DailyScheduleAssignment $assignment): bool
    {
        return $this->update($user, $assignment);
    }
}
