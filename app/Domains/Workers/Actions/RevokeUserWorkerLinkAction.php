<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\User;
use App\Models\UserWorkerLink;

class RevokeUserWorkerLinkAction
{
    public function handle(Company $company, User $user): bool
    {
        return UserWorkerLink::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'revoked']) > 0;
    }
}
