<?php

namespace App\Domains\Integrations\Actions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeCompanyApiTokenAction
{
    public function handle(User $user, Company $company, int $tokenId): void
    {
        $token = PersonalAccessToken::query()
            ->whereKey($tokenId)
            ->where('company_id', $company->id)
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $user->id)
            ->first();

        if (! $token) {
            throw ValidationException::withMessages([
                'token' => 'La credencial no pertenece al usuario ni a la empresa activa.',
            ]);
        }

        $token->delete();
    }
}
