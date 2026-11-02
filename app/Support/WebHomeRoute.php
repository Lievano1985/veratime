<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;

class WebHomeRoute
{
    public static function forUser(User $user, ?Company $company = null): string
    {
        $company ??= $user->activeCompanies()->whereKey(session('current_company_id'))->first() ?? $user->defaultCompany();

        return $company && $user->roleKeyForCompany($company) === RoleKey::TRABAJADOR
            ? 'personal.dashboard' : 'dashboard';
    }
}
