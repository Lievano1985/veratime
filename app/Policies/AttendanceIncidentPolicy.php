<?php

namespace App\Policies;

use App\Models\AttendanceIncident;
use App\Models\Company;
use App\Models\User;
use App\Support\RoleKey;

class AttendanceIncidentPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManage($user, $company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManage($user, $company);
    }

    public function cancel(User $user, AttendanceIncident $incident): bool
    {
        return $incident->company && $this->canManage($user, $incident->company);
    }

    private function canManage(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
