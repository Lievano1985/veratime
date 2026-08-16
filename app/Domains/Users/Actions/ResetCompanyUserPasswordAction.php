<?php

namespace App\Domains\Users\Actions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class ResetCompanyUserPasswordAction
{
    public function handle(Company $company, User $actor, User $targetUser, string $password): void
    {
        Gate::authorize('resetPassword', [$targetUser, $company]);

        $targetUser->forceFill([
            'password' => Hash::make($password),
        ])->save();
    }
}
