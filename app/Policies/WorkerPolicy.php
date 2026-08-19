<?php

namespace App\Policies;

use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Models\Center;
use App\Models\Company;
use App\Models\User;
use App\Support\RoleKey;
use App\Models\Worker;

class WorkerPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canAccessWorkers($user, $company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageCompanyWorkers($user, $company);
    }

    public function update(User $user, Worker $worker): bool
    {
        return $this->canManageWorker($user, $worker);
    }

    public function createForCenter(User $user, Company $company, Center $center): bool
    {
        return $center->company_id === $company->id
            && $center->status === 'active'
            && app(ScopedOperationalAccess::class)->canOperateFullCenter($user, $company, $center);
    }

    public function terminate(User $user, Worker $worker): bool
    {
        return $this->update($user, $worker);
    }

    public function delete(User $user, Worker $worker): bool
    {
        return $this->update($user, $worker);
    }

    private function canManageCompanyWorkers(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
    }

    private function canAccessWorkers(User $user, Company $company): bool
    {
        return $this->canManageCompanyWorkers($user, $company)
            || app(ScopedOperationalAccess::class)->canOperateCompany($user, $company);
    }

    private function canManageWorker(User $user, Worker $worker): bool
    {
        $company = $worker->company;

        if (! $company || $company->status !== 'active' || ! $user->belongsToCompany($company)) {
            return false;
        }

        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return true;
        }

        if (! in_array($user->roleKeyForCompany($company), RoleKey::scopedOperators(), true)) {
            return false;
        }

        $relationship = $worker->activeEmploymentRelationship;

        return $relationship
            && app(ScopedOperationalAccess::class)->canOperateRelationship($user, $company, $relationship);
    }
}
