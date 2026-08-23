<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Support\RoleKey;

class CompanyPolicy
{
    public function create(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->companiesWithActiveMembership()
            ->get()
            ->contains(fn (Company $company) => in_array($user->roleKeyForCompanyMembership($company), RoleKey::companyManagers(), true));
    }

    public function view(User $user, Company $company): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $company->hasActiveCustomerAccount()
            && $user->hasActiveMembershipInCompany($company);
    }

    public function update(User $user, Company $company): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $company->hasActiveCustomerAccount()
            && $user->hasActiveMembershipInCompany($company)
            && in_array($user->roleKeyForCompanyMembership($company), RoleKey::companyManagers(), true);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->isSuperAdmin();
    }
}
