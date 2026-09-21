<?php

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\EmploymentRelationship;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;

beforeEach(function (): void {
    $this->seed(ProductSeeder::class);
});

function createApiWorkerWithRelationship(Company $company): array
{
    $center = Center::factory()->create(['company_id' => $company->id]);
    $worker = Worker::factory()->create(['company_id' => $company->id, 'status' => 'active']);
    EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'status' => 'active',
    ]);

    return [$worker, $center];
}

function timeEventApiToken(User $user, Company $company): string
{
    return app(IssueCompanyApiTokenAction::class)
        ->handle($user, $company, 'time-event-api-test', ['time-events:write'])
        ->plainTextToken;
}

it('registers a time event through the API tenant and domain action', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    [$worker, $center] = createApiWorkerWithRelationship($company);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);

    $response = $this->withToken(timeEventApiToken($user, $company))
        ->withHeader('Idempotency-Key', 'device-001-20260917-080000')
        ->postJson('/api/v1/time/time-events', [
            'employee_code' => $worker->employee_code,
            'event_type' => 'clock_in',
            'occurred_at' => '2026-09-17T08:00:00-07:00',
            'timezone' => 'America/Hermosillo',
            'center_id' => $center->id,
            'external_id' => 'CLOCK-001',
            'device' => ['code' => 'CLOCK-01', 'name' => 'Acceso principal'],
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.worker_id', (string) $worker->id)
        ->assertJsonPath('data.event_type', 'clock_in')
        ->assertJsonPath('data.source', 'api')
        ->assertJsonPath('meta.idempotent_replay', false)
        ->assertJsonStructure(['meta' => ['trace_id']]);

    $this->assertDatabaseHas('time_events', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'source' => 'api',
        'idempotency_key' => 'device-001-20260917-080000',
    ]);
});

it('returns the original event when an API client retries the same idempotency key', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    [$worker] = createApiWorkerWithRelationship($company);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = timeEventApiToken($user, $company);
    $payload = [
        'worker_id' => $worker->id,
        'event_type' => 'clock_in',
        'occurred_at' => '2026-09-17T08:00:00-07:00',
    ];

    $first = $this->withToken($token)
        ->withHeader('Idempotency-Key', 'retry-001')
        ->postJson('/api/v1/time/time-events', $payload)
        ->assertCreated();

    $this->withToken($token)
        ->withHeader('Idempotency-Key', 'retry-001')
        ->postJson('/api/v1/time/time-events', $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'))
        ->assertJsonPath('meta.idempotent_replay', true);

    expect($company->timeEvents()->count())->toBe(1);
});

it('does not register a time event for a worker from another company', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    [$otherWorker] = createApiWorkerWithRelationship(Company::factory()->create());
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);

    $this->withToken(timeEventApiToken($user, $company))
        ->postJson('/api/v1/time/time-events', [
            'worker_id' => $otherWorker->id,
            'event_type' => 'clock_in',
            'occurred_at' => '2026-09-17T08:00:00-07:00',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('event');

    expect($company->timeEvents()->count())->toBe(0);
});

it('voids an event without deleting it and cannot void an event from another company', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    [$worker] = createApiWorkerWithRelationship($company);
    $event = TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'status' => 'valid',
    ]);
    $foreignEvent = TimeEvent::factory()->create();
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = timeEventApiToken($user, $company);

    $this->withToken($token)->postJson('/api/v1/time/time-events/'.$event->id.'/void', [
        'reason' => 'Captura duplicada confirmada.',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', (string) $event->id)
        ->assertJsonPath('data.status', 'voided');

    $this->assertDatabaseHas('time_events', [
        'id' => $event->id,
        'status' => 'voided',
        'void_reason' => 'Captura duplicada confirmada.',
    ]);
    $this->withToken($token)->postJson('/api/v1/time/time-events/'.$foreignEvent->id.'/void', [
        'reason' => 'No debe acceder a este evento.',
    ])->assertNotFound();
});

it('approves or rejects pending manual events through the existing review actions', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    [$worker] = createApiWorkerWithRelationship($company);
    $approved = TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'source' => 'admin_manual',
        'status' => 'pending_review',
    ]);
    $rejected = TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'source' => 'admin_manual',
        'status' => 'pending_review',
    ]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    $token = timeEventApiToken($user, $company);

    $this->withToken($token)->postJson('/api/v1/time/time-events/'.$approved->id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'valid');
    $this->withToken($token)->postJson('/api/v1/time/time-events/'.$rejected->id.'/reject', [
        'reason' => 'Registro manual duplicado.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'ignored');

    $this->assertDatabaseHas('time_events', ['id' => $approved->id, 'status' => 'valid']);
    $this->assertDatabaseHas('time_events', ['id' => $rejected->id, 'status' => 'ignored']);
});
