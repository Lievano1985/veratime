<?php

use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\Worker;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;

beforeEach(function (): void {
    $this->seed(ProductSeeder::class);
});

it('issues a self-read token to a linked worker from the authenticated web session', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);

    $response = $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('personal-access-token.store'));

    $response->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.abilities.0', 'self:read')
        ->assertJsonPath('data.abilities.1', 'self:write');

    $this->postJson('/api/v1/time/me/time-events', [
        'event_type' => 'clock_in',
        'occurred_at' => '2026-09-18T15:00:00Z',
    ])->assertForbidden();

    $this->assertDatabaseCount('personal_access_tokens', 1);
});

it('does not issue a personal token without an active worker link', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->postJson(route('personal-access-token.store'))
        ->assertForbidden();
});
