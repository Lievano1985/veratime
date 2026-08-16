<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Support\RoleKey;

class UserPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManageUsers($user, $company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageUsers($user, $company);
    }

    public function update(User $user, User $targetUser, Company $company): bool
    {
        return $this->canManageUsers($user, $company)
            && $targetUser->companies()->whereKey($company->id)->exists();
    }

    public function resetPassword(User $user, User $targetUser, Company $company): bool
    {
        return $this->update($user, $targetUser, $company);
    }

    private function canManageUsers(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), [
                ...RoleKey::companyManagers(),
                RoleKey::SUPER_ADMIN,
            ], true);
    }
}
