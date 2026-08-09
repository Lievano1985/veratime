<?php

namespace App\Policies;

use App\Models\AttendancePeriod;
use App\Models\Company;
use App\Models\User;
use App\Support\RoleKey;

class AttendancePeriodPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManageAttendancePeriods($user, $company);
    }

    public function view(User $user, AttendancePeriod $period): bool
    {
        return $this->canManageAttendancePeriods($user, $period->company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageAttendancePeriods($user, $company);
    }

    public function cancel(User $user, AttendancePeriod $period): bool
    {
        return $period->status === AttendancePeriod::STATUS_OPEN
            && $this->canManageAttendancePeriods($user, $period->company);
    }

    public function validateForClosing(User $user, AttendancePeriod $period): bool
    {
        return in_array($period->status, [AttendancePeriod::STATUS_OPEN, AttendancePeriod::STATUS_READY], true)
            && $this->canManageAttendancePeriods($user, $period->company);
    }

    public function close(User $user, AttendancePeriod $period): bool
    {
        return in_array($period->status, [AttendancePeriod::STATUS_OPEN, AttendancePeriod::STATUS_READY], true)
            && $this->canManageAttendancePeriods($user, $period->company);
    }

    private function canManageAttendancePeriods(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
