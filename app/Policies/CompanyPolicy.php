<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Support\RoleKey;

class CompanyPolicy
{
    public function create(User $user): bool
    {
        return $user->companiesWithActiveMembership()
            ->get()
            ->contains(fn (Company $company) => in_array($user->roleKeyForCompanyMembership($company), RoleKey::companyManagers(), true));
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasActiveMembershipInCompany($company);
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasActiveMembershipInCompany($company)
            && in_array($user->roleKeyForCompanyMembership($company), RoleKey::companyManagers(), true);
    }
}
