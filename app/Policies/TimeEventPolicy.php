<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\TimeEvent;
use App\Models\User;
use App\Support\RoleKey;

class TimeEventPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canAccessTimeEvents($user, $company);
    }

    public function view(User $user, TimeEvent $timeEvent): bool
    {
        return $this->canAccessTimeEvents($user, $timeEvent->company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canAccessTimeEvents($user, $company);
    }

    public function void(User $user, TimeEvent $timeEvent): bool
    {
        return $this->canAccessTimeEvents($user, $timeEvent->company)
            && ! $timeEvent->isVoided();
    }

    public function approve(User $user, TimeEvent $timeEvent): bool
    {
        return $this->canAccessTimeEvents($user, $timeEvent->company)
            && $timeEvent->source === 'admin_manual'
            && $timeEvent->status === 'pending_review'
            && ! $timeEvent->isVoided();
    }

    public function reject(User $user, TimeEvent $timeEvent): bool
    {
        return $this->approve($user, $timeEvent);
    }

    private function canAccessTimeEvents(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }
}
