<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\PayrollExportTemplate;
use App\Models\User;
use App\Support\RoleKey;

class PayrollExportTemplatePolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManage($user, $company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManage($user, $company);
    }

    public function update(User $user, PayrollExportTemplate $template): bool
    {
        return ! $template->is_system && $this->canManage($user, $template->company);
    }

    private function canManage(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
