<?php

namespace App\Domains\Tenancy\Actions;

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class SetCurrentCompanyAction
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $user, Company $company): void
    {
        if (! $user->belongsToCompany($company)) {
            throw new AuthorizationException('The selected company is not available for this user.');
        }

        session(['current_company_id' => $company->id]);

        app(CurrentCompany::class)->set($company);
    }
}
