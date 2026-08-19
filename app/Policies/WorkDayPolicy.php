<?php

namespace App\Policies;

use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkDay;
use App\Support\RoleKey;

class WorkDayPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canViewWorkDays($user, $company);
    }

    public function view(User $user, WorkDay $workDay): bool
    {
        if (! $workDay->company || ! $this->canViewWorkDays($user, $workDay->company)) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($workDay->company), RoleKey::companyManagers(), true)) {
            return true;
        }

        if ($workDay->employmentRelationship) {
            return app(ScopedOperationalAccess::class)->canConsultRelationship($user, $workDay->company, $workDay->employmentRelationship, $workDay->work_date?->toDateString());
        }

        return app(ScopedOperationalAccess::class)->canConsultCenter($user, $workDay->company, $workDay->center, $workDay->work_date?->toDateString());
    }

    private function canViewWorkDays(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)
                || app(ScopedOperationalAccess::class)->canConsultCompany($user, $company));
    }
}
