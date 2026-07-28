<?php

namespace App\Policies;

use App\Models\Center;
use App\Models\Company;
use App\Models\User;
use App\Support\RoleKey;

class CenterPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManageCompanyCenters($user, $company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageCompanyCenters($user, $company);
    }

    public function update(User $user, Center $center): bool
    {
        return $this->canManageCompanyCenters($user, $center->company);
    }

    public function inactivate(User $user, Center $center): bool
    {
        return $this->update($user, $center);
    }

    public function delete(User $user, Center $center): bool
    {
        return $this->update($user, $center);
    }

    private function canManageCompanyCenters(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
