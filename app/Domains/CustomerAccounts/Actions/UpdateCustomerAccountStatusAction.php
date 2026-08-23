<?php

namespace App\Domains\CustomerAccounts\Actions;

use App\Models\CustomerAccount;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateCustomerAccountStatusAction
{
    public function handle(User $actor, CustomerAccount $customerAccount, string $status): CustomerAccount
    {
        Gate::authorize('update', $customerAccount);

        if (! in_array($status, ['active', 'suspended', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Selecciona un estado valido para la cuenta cliente.',
            ]);
        }

        $customerAccount->forceFill(['status' => $status])->save();

        return $customerAccount->refresh();
    }
}
