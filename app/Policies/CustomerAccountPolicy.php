<?php

namespace App\Policies;

use App\Models\CustomerAccount;
use App\Models\User;

class CustomerAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, CustomerAccount $customerAccount): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, CustomerAccount $customerAccount): bool
    {
        return $user->isSuperAdmin();
    }
}
