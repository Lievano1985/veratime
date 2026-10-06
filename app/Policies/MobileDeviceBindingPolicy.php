<?php

namespace App\Policies;

use App\Models\MobileDeviceBinding;
use App\Models\User;
use App\Support\RoleKey;

class MobileDeviceBindingPolicy
{
    public function revoke(User $user, MobileDeviceBinding $binding): bool
    {
        return $user->status === 'active'
            && $user->belongsToCompany($binding->company)
            && in_array($user->roleKeyForCompany($binding->company), [
                ...RoleKey::companyManagers(),
                RoleKey::SUPER_ADMIN,
            ], true);
    }
}
