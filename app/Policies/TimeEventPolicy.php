<?php

namespace App\Policies;

use App\Domains\Organization\Support\ScopedOperationalAccess;
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
        return $this->canAccessTimeEvent($user, $timeEvent);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canAccessTimeEvents($user, $company);
    }

    public function void(User $user, TimeEvent $timeEvent): bool
    {
        return $this->canAccessTimeEvent($user, $timeEvent)
            && ! $timeEvent->isVoided();
    }

    public function approve(User $user, TimeEvent $timeEvent): bool
    {
        return $this->canAccessTimeEvent($user, $timeEvent)
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
            && $user->status === 'active'
            && $user->belongsToCompany($company)
            && (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)
                || app(ScopedOperationalAccess::class)->canOperateCompany($user, $company));
    }

    private function canAccessTimeEvent(User $user, TimeEvent $timeEvent): bool
    {
        $company = $timeEvent->company;

        if (! $company || ! $this->canAccessTimeEvents($user, $company)) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return true;
        }

        return app(ScopedOperationalAccess::class)->canOperateFullCenter($user, $company, $timeEvent->center);
    }
}
