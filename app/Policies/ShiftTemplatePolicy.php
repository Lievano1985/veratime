<?php

namespace App\Policies;

use App\Domains\Organization\Actions\ResolveUserOperationalScopeAction;
use App\Models\Company;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Support\RoleKey;
use InvalidArgumentException;

class ShiftTemplatePolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManageCompanyTemplates($user, $company)
            || $this->hasSupervisorScope($user, $company);
    }

    public function view(User $user, ShiftTemplate $template): bool
    {
        if ($template->company?->status !== 'active' || ! $user->belongsToCompany($template->company)) {
            return false;
        }

        if ($this->canManageCompanyTemplates($user, $template->company)) {
            return true;
        }

        return $template->status === 'active' && $this->hasSupervisorScope($user, $template->company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageCompanyTemplates($user, $company);
    }

    public function update(User $user, ShiftTemplate $template): bool
    {
        return $this->canManageCompanyTemplates($user, $template->company);
    }

    public function inactivate(User $user, ShiftTemplate $template): bool
    {
        return $this->update($user, $template);
    }

    public function delete(User $user, ShiftTemplate $template): bool
    {
        return $this->update($user, $template);
    }

    public function reactivate(User $user, ShiftTemplate $template): bool
    {
        return $this->update($user, $template);
    }

    private function canManageCompanyTemplates(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }

    private function hasSupervisorScope(User $user, Company $company): bool
    {
        if ($company->status !== 'active'
            || $user->status !== 'active'
            || ! $user->belongsToCompany($company)
            || $user->roleKeyForCompany($company) !== RoleKey::SUPERVISOR) {
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
