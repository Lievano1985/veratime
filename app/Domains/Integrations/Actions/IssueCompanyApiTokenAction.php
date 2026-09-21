<?php

namespace App\Domains\Integrations\Actions;

use App\Domains\Products\Support\ProductAccess;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class IssueCompanyApiTokenAction
{
    public function __construct(
        private readonly ProductAccess $productAccess,
    ) {}

    /**
     * @param  list<string>  $abilities
     */
    public function handle(User $user, Company $company, string $name, array $abilities): NewAccessToken
    {
        if ($user->status !== 'active' || ! $user->belongsToCompany($company)) {
            throw ValidationException::withMessages([
                'company' => 'El usuario no puede emitir un token para esta empresa.',
            ]);
        }

        if (! $this->productAccess->companyHasOperationalProduct($company, 'time')) {
            throw ValidationException::withMessages([
                'company' => 'VERA Time no esta activo para esta empresa.',
            ]);
        }

        $plainTextToken = Str::random(40);
        $token = new PersonalAccessToken;
        $token->forceFill([
            'company_id' => $company->id,
            'name' => trim($name),
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
        ]);
        $user->tokens()->save($token);

        return new NewAccessToken($token, $token->getKey().'|'.$plainTextToken);
    }
}
