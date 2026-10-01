<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\KioskDevice;
use App\Models\User;
use App\Support\RoleKey;

class KioskDevicePolicy
{
    public function create(User $user, Company $company): bool
    {
        return $this->canManage($user, $company);
    }

    public function update(User $user, KioskDevice $device): bool
    {
        return $this->canManage($user, $device->company);
    }

    private function canManage(User $user, ?Company $company): bool
    {
        return $company?->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
