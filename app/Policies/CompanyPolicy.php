<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Support\RoleKey;

class CompanyPolicy
{
    public function create(User $user): bool
    {
        return $user->activeCompanies()
            ->get()
            ->contains(fn (Company $company) => in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true));
    }

    public function view(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company);
    }

    public function update(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
