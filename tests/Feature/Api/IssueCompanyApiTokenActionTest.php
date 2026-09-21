<?php

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\User;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(ProductSeeder::class);
});

it('issues an API token bound to an operational Time company', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create([
        'status' => 'active',
        'global_role' => RoleKey::SUPER_ADMIN,
    ]);

    $issued = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'mobile', ['workers:read']);

    expect($issued->plainTextToken)->toStartWith($issued->accessToken->id.'|')
        ->and($issued->accessToken->company_id)->toBe($company->id)
        ->and($issued->accessToken->can('workers:read'))->toBeTrue();
});

it('does not issue a company token to a user without membership', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create([
        'status' => 'active',
        'global_role' => null,
    ]);

    expect(fn () => app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'mobile', ['workers:read']))
        ->toThrow(ValidationException::class);
});

it('does not issue a company token to an inactive user', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create([
        'status' => 'inactive',
        'global_role' => RoleKey::SUPER_ADMIN,
    ]);

    expect(fn () => app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'mobile', ['self:read']))
        ->toThrow(ValidationException::class);
});
