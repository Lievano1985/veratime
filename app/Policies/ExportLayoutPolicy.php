<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\ExportLayout;
use App\Models\User;
use App\Support\RoleKey;

class ExportLayoutPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $company->status === 'active' && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->viewAny($user, $company);
    }

    public function update(User $user, ExportLayout $layout): bool
    {
        return $this->viewAny($user, $layout->company);
    }
}
