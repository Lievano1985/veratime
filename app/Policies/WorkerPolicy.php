<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Models\Worker;

class WorkerPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->canManageCompanyWorkers($user, $company);
    }

    public function create(User $user, Company $company): bool
    {
        return $this->canManageCompanyWorkers($user, $company);
    }

    public function update(User $user, Worker $worker): bool
    {
        return $this->canManageCompanyWorkers($user, $worker->company);
    }

    public function terminate(User $user, Worker $worker): bool
    {
        return $this->update($user, $worker);
    }

    private function canManageCompanyWorkers(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), ['owner', 'admin'], true);
    }
}
