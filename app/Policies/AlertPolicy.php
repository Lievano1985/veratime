<?php

namespace App\Policies;

use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Models\Alert;
use App\Models\Company;
use App\Models\User;
use App\Support\RoleKey;

class AlertPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canViewAlerts($user, $company);
    }

    public function view(User $user, Alert $alert): bool
    {
        return $this->canViewAlert($user, $alert);
    }

    public function resolve(User $user, Alert $alert): bool
    {
        if (! $this->canViewAlert($user, $alert)) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($alert->company), RoleKey::companyManagers(), true)) {
            return true;
        }

        return $alert->workDay
            && app(ScopedOperationalAccess::class)->canOperateFullCenter($user, $alert->company, $alert->workDay->center);
    }

    private function canViewAlerts(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)
                || app(ScopedOperationalAccess::class)->canConsultCompany($user, $company));
    }

    private function canViewAlert(User $user, Alert $alert): bool
    {
        $company = $alert->company;

        if (! $company || ! $this->canViewAlerts($user, $company) || ! $alert->workDay) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return true;
        }

        if ($alert->workDay->employmentRelationship) {
            return app(ScopedOperationalAccess::class)->canConsultRelationship($user, $company, $alert->workDay->employmentRelationship, $alert->workDay->work_date?->toDateString());
        }

        return app(ScopedOperationalAccess::class)->canConsultCenter($user, $company, $alert->workDay->center, $alert->workDay->work_date?->toDateString());
    }
}