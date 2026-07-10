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
        return $mandatoryRestDay->company !== null
            && $this->canManageMandatoryRestDays($user, $mandatoryRestDay->company);
    }

    public function inactivate(User $user, MandatoryRestDay $mandatoryRestDay): bool
    {
        return $this->update($user, $mandatoryRestDay);
    }

    private function canManageMandatoryRestDays(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), ['owner', 'admin'], true);
    }
}
