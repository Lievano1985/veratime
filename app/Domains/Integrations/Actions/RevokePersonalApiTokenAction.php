<?php

namespace App\Domains\Integrations\Actions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class RevokePersonalApiTokenAction
{
    public function handle(User $user, Company $company, PersonalAccessToken $token): void
    {
        if ($token->company_id !== $company->id
            || $token->tokenable_type !== User::class
            || $token->tokenable_id !== $user->id
            || ! $token->can('self:read')) {
            throw ValidationException::withMessages([
                'token' => 'La credencial personal no pertenece al usuario ni a la empresa activa.',
            ]);
        }

        $token->delete();
    }
}
