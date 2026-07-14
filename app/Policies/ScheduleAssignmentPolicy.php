<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Support\RoleKey;

class ScheduleAssignmentPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManageCompanyScheduleAssignments($user, $company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageCompanyScheduleAssignments($user, $company);
    }

    public function update(User $user, ScheduleAssignment $scheduleAssignment): bool
    {
        return $this->canManageCompanyScheduleAssignments($user, $scheduleAssignment->company);
    }

    public function inactivate(User $user, ScheduleAssignment $scheduleAssignment): bool
    {
        return $this->update($user, $scheduleAssignment);
    }

    private function canManageCompanyScheduleAssignments(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
