<?php

namespace App\Policies;

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
        return $alert->company
            && $this->canViewAlerts($user, $alert->company);
    }

    private function canViewAlerts(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
