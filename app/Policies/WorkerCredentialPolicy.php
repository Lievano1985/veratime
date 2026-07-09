<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerCredential;

class WorkerCredentialPolicy
{
    public function create(User $user, Company $company, Worker $worker): bool
    {
        return $worker->company_id === $company->id
            && $this->canManageCompanyCredentials($user, $company);
    }

    public function update(User $user, WorkerCredential $credential): bool
    {
        return $this->canManageCompanyCredentials($user, $credential->company);
    }

    public function reset(User $user, WorkerCredential $credential): bool
    {
        return $this->update($user, $credential);
    }

    public function block(User $user, WorkerCredential $credential): bool
    {
        return $this->update($user, $credential);
    }

    private function canManageCompanyCredentials(User $user, Company $company): bool
    {
        return $company->status === 'active'
            && $user->belongsToCompany($company)
            && in_array($user->roleKeyForCompany($company), ['owner', 'admin'], true);
    }
}
