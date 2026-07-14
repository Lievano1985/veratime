<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\MandatoryRestDay;
use App\Models\User;

class MandatoryRestDayPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManageMandatoryRestDays($user, $company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageMandatoryRestDays($user, $company);
    }

    public function update(User $user, MandatoryRestDay $mandatoryRestDay): bool
    {
        if ($this->isGlobalCatalogRecord($mandatoryRestDay)) {
            return $this->hasAnyRole($user, ['super_admin']);
        }

        return $mandatoryRestDay->company !== null
            && $mandatoryRestDay->type === 'company_internal'
            && $mandatoryRestDay->scope === 'company'
            && $this->canManageCompanyInternalRestDays($user, $mandatoryRestDay->company);
    }

    public function inactivate(User $user, MandatoryRestDay $mandatoryRestDay): bool
    {
        return $this->update($user, $mandatoryRestDay);
    }

    private function canManageMandatoryRestDays(User $user, Company $company): bool
    {
        return $this->canManageCompanyInternalRestDays($user, $company)
            || $this->hasAnyRole($user, ['super_admin']);
    }

    private function canManageCompanyInternalRestDays(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), ['owner', 'admin'], true);
    }

    private function isGlobalCatalogRecord(MandatoryRestDay $mandatoryRestDay): bool
    {
        return $mandatoryRestDay->company_id === null
            && (
                in_array($mandatoryRestDay->scope, ['national', 'state'], true)
                || $mandatoryRestDay->type === 'electoral'
            );
    }

    private function hasAnyRole(User $user, array $roleKeys): bool
    {
        return $user->activeCompanies()
            ->get()
            ->contains(fn (Company $company) => in_array($user->roleKeyForCompany($company), $roleKeys, true));
    }
}
