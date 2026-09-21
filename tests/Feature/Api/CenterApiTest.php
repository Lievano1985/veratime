<?php

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\User;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;

beforeEach(function (): void {
    $this->seed(ProductSeeder::class);
});

it('lists and shows centers only from the company resolved by the token', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $visibleCenter = Center::factory()->create(['company_id' => $company->id, 'code' => 'NORTE', 'name' => 'Centro Norte']);
    $hiddenCenter = Center::factory()->create(['code' => 'SUR', 'name' => 'Centro Sur']);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'centers', ['centers:read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/time/centers?search=Norte')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $visibleCenter->id)
        ->assertJsonMissing(['id' => (string) $hiddenCenter->id]);
    $this->withToken($token)->getJson('/api/v1/time/centers/'.$visibleCenter->id)
        ->assertOk()
        ->assertJsonPath('data.code', 'NORTE');
    $this->withToken($token)->getJson('/api/v1/time/centers/'.$hiddenCenter->id)->assertNotFound();
});

it('requires the center read scope', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'wrong-scope', ['workers:read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/time/centers')->assertForbidden();
});
