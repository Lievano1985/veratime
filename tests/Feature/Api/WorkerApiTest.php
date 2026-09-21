<?php

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\EmploymentRelationship;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;

beforeEach(function (): void {
    $this->seed(ProductSeeder::class);
});

function issueWorkerApiToken(User $user, Company $company, array $abilities): string
{
    return app(IssueCompanyApiTokenAction::class)
        ->handle($user, $company, 'api-test', $abilities)
        ->plainTextToken;
}

it('lists only workers from the company resolved by the token', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $otherCompany = Company::factory()->create();
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $visibleWorker = Worker::factory()->create(['company_id' => $company->id, 'full_name' => 'Ana Visible']);
    $hiddenWorker = Worker::factory()->create(['company_id' => $otherCompany->id, 'full_name' => 'Beto Oculto']);

    $response = $this->withToken(issueWorkerApiToken($user, $company, ['workers:read']))
        ->getJson('/api/v1/time/workers?search=');

    $response
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $visibleWorker->id)
        ->assertJsonMissing(['id' => (string) $hiddenWorker->id])
        ->assertJsonStructure(['meta' => ['trace_id'], 'links'])
        ->assertHeader('X-Trace-Id');
});

it('creates a worker through the same action used by the web interface', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);

    $response = $this->withToken(issueWorkerApiToken($user, $company, ['workers:write']))
        ->postJson('/api/v1/time/workers', [
            'employee_code' => 'API-001',
            'full_name' => 'Andrea API',
            'email' => 'andrea@example.test',
            'center_id' => $center->id,
            'position_name' => 'Operadora',
            'started_at' => '2026-09-01',
            'external_id' => 'EXT-001',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.employee_code', 'API-001')
        ->assertJsonPath('data.source', 'api')
        ->assertJsonPath('data.center.id', (string) $center->id)
        ->assertJsonStructure(['meta' => ['trace_id']]);

    $this->assertDatabaseHas('workers', [
        'company_id' => $company->id,
        'employee_code' => 'API-001',
        'source' => 'api',
    ]);
    $this->assertDatabaseHas('employment_relationships', [
        'company_id' => $company->id,
        'center_id' => $center->id,
        'status' => 'active',
        'source' => 'api',
    ]);
});

it('updates a worker through the shared worker and relationship action', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $center = Center::factory()->create(['company_id' => $company->id]);
    $worker = Worker::factory()->create([
        'company_id' => $company->id,
        'employee_code' => 'API-UPDATE',
        'full_name' => 'Nombre anterior',
    ]);
    EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Operadora',
        'started_at' => '2026-01-01',
        'status' => 'active',
    ]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = issueWorkerApiToken($user, $company, ['workers:write']);

    $this->withToken($token)->putJson('/api/v1/time/workers/'.$worker->id, [
        'employee_code' => 'API-UPDATE',
        'full_name' => 'Nombre actualizado',
        'email' => 'actualizada@example.test',
        'center_id' => $center->id,
        'position_name' => 'Operadora',
        'started_at' => '2026-01-01',
        'status' => 'active',
    ])
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Nombre actualizado')
        ->assertJsonPath('data.center.id', (string) $center->id);

    $this->assertDatabaseHas('workers', [
        'id' => $worker->id,
        'full_name' => 'Nombre actualizado',
        'email' => 'actualizada@example.test',
    ]);
});

it('rejects a relationship change without a reason or with a foreign center', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $center = Center::factory()->create(['company_id' => $company->id]);
    $otherCenter = Center::factory()->create();
    $worker = Worker::factory()->create(['company_id' => $company->id, 'employee_code' => 'API-CHANGE']);
    EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Operadora',
        'started_at' => '2026-01-01',
        'status' => 'active',
    ]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = issueWorkerApiToken($user, $company, ['workers:write']);
    $payload = [
        'employee_code' => 'API-CHANGE',
        'full_name' => $worker->full_name,
        'center_id' => $center->id,
        'position_name' => 'Supervisora',
        'started_at' => '2026-01-01',
        'status' => 'active',
    ];

    $this->withToken($token)->putJson('/api/v1/time/workers/'.$worker->id, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('relationship_change_reason');
    $this->withToken($token)->putJson('/api/v1/time/workers/'.$worker->id, [
        ...$payload,
        'center_id' => $otherCenter->id,
        'relationship_change_reason' => 'Centro capturado incorrectamente.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('center_id');
});

it('does not update a worker outside the company bound to the token', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $center = Center::factory()->create(['company_id' => $company->id]);
    $foreignWorker = Worker::factory()->create(['employee_code' => 'FOREIGN-UPDATE']);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);

    $this->withToken(issueWorkerApiToken($user, $company, ['workers:write']))
        ->putJson('/api/v1/time/workers/'.$foreignWorker->id, [
            'employee_code' => 'FOREIGN-UPDATE',
            'full_name' => 'No debe actualizarse',
            'center_id' => $center->id,
            'started_at' => '2026-01-01',
            'status' => 'active',
        ])
        ->assertNotFound();
});

it('returns a worker detail only from the company bound to the token', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $foreignWorker = Worker::factory()->create();
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = issueWorkerApiToken($user, $company, ['workers:read']);

    $this->withToken($token)->getJson('/api/v1/time/workers/'.$worker->id)
        ->assertOk()
        ->assertJsonPath('data.id', (string) $worker->id);
    $this->withToken($token)->getJson('/api/v1/time/workers/'.$foreignWorker->id)
        ->assertNotFound();
});

it('lists the employment relationship history only for a worker in the token company', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $center = Center::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'started_at' => '2026-01-01',
        'status' => 'ended',
        'ended_at' => '2026-06-30',
    ]);
    $foreignWorker = Worker::factory()->create();
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = issueWorkerApiToken($user, $company, ['workers:read']);

    $this->withToken($token)->getJson('/api/v1/time/workers/'.$worker->id.'/relationships')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $relationship->id)
        ->assertJsonPath('data.0.center.id', (string) $center->id);
    $this->withToken($token)->getJson('/api/v1/time/workers/'.$foreignWorker->id.'/relationships')->assertNotFound();
});

it('rejects a token without the required worker scope', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);

    $this->withToken(issueWorkerApiToken($user, $company, ['workers:read']))
        ->postJson('/api/v1/time/workers', [])
        ->assertForbidden();
});

it('does not accept a center from another company when creating a worker', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $otherCenter = Center::factory()->create();
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);

    $this->withToken(issueWorkerApiToken($user, $company, ['workers:write']))
        ->postJson('/api/v1/time/workers', [
            'employee_code' => 'API-002',
            'full_name' => 'Centro Ajeno',
            'center_id' => $otherCenter->id,
            'started_at' => '2026-09-01',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('center_id');
});
