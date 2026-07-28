<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\Schedule;
use App\Models\User;
use App\Support\RoleKey;

class SchedulePolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManageCompanySchedules($user, $company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageCompanySchedules($user, $company);
    }

    public function update(User $user, Schedule $schedule): bool
    {
        return $this->canManageCompanySchedules($user, $schedule->company);
    }

    public function inactivate(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    public function delete(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    private function canManageCompanySchedules(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
