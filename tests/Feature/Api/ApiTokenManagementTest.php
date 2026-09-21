<?php

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Domains\Integrations\Actions\RevokeCompanyApiTokenAction;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function (): void {
    $this->seed(ProductSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('lets a company manager issue and revoke their company API token', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);

    expect($user->can('manageApiTokens', $company))->toBeTrue();

    $issued = app(IssueCompanyApiTokenAction::class)->handle(
        $user,
        $company,
        'Reloj principal',
        ['workers:read', 'time-events:write'],
    );

    expect($issued->plainTextToken)->toContain('|');

    $token = PersonalAccessToken::query()
        ->where('company_id', $company->id)
        ->where('tokenable_id', $user->id)
        ->firstOrFail();

    app(RevokeCompanyApiTokenAction::class)->handle($user, $company, $token->id);

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
});

it('does not allow a worker role to manage API tokens or revoke another user token', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['status' => 'active']);
    $workerRole = Role::query()->where('key', RoleKey::TRABAJADOR)->firstOrFail();
    $company->users()->attach($user, ['role_id' => $workerRole->id, 'status' => 'active', 'is_default' => true]);

    expect($user->can('manageApiTokens', $company))->toBeFalse();

    $manager = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $issued = app(IssueCompanyApiTokenAction::class)->handle($manager, $company, 'Propio', ['workers:read']);

    expect(fn () => app(RevokeCompanyApiTokenAction::class)->handle($user, $company, $issued->accessToken->id))
        ->toThrow(ValidationException::class);
});
