<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Models\WorkDay;
use App\Support\RoleKey;

class WorkDayPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canViewWorkDays($user, $company);
    }

    public function view(User $user, WorkDay $workDay): bool
    {
        return $workDay->company
            && $this->canViewWorkDays($user, $workDay->company);
    }

    private function canViewWorkDays(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
