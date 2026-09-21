<?php

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountProduct;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\WorkDay;
use App\Models\Worker;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;

beforeEach(function (): void {
    $this->seed(ProductSeeder::class);
});

it('only returns the linked worker events to a personal token', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $other = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
    $own = TimeEvent::factory()->create(['company_id' => $company->id, 'worker_id' => $worker->id]);
    $hidden = TimeEvent::factory()->create(['company_id' => $company->id, 'worker_id' => $other->id]);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal', ['self:read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/time/me/time-events')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $own->id)
        ->assertJsonMissing(['id' => (string) $hidden->id])
        ->assertJsonStructure(['meta' => ['current_page', 'per_page', 'total', 'trace_id'], 'links']);
});

it('registers a personal PWA event for only the linked worker and replays idempotently', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $other = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal-write', ['self:read', 'self:write'])->plainTextToken;
    $payload = ['event_type' => 'clock_in', 'occurred_at' => '2026-09-18T15:00:00Z', 'worker_id' => $other->id, 'device' => ['code' => 'phone-01']];

    $this->withToken($token)->withHeader('Idempotency-Key', 'pwa-20260918-clock-in')->postJson('/api/v1/time/me/time-events', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('worker_id');

    unset($payload['worker_id']);
    $first = $this->withToken($token)->withHeader('Idempotency-Key', 'pwa-20260918-clock-in')->postJson('/api/v1/time/me/time-events', $payload);

    $first->assertCreated()
        ->assertJsonPath('data.worker_id', (string) $worker->id)
        ->assertJsonPath('data.source', 'pwa')
        ->assertJsonPath('meta.idempotent_replay', false);
    $eventId = $first->json('data.id');
    $this->assertDatabaseHas('time_events', ['id' => $eventId, 'company_id' => $company->id, 'worker_id' => $worker->id, 'source_user_id' => $user->id, 'source' => 'pwa']);

    $this->withToken($token)->withHeader('Idempotency-Key', 'pwa-20260918-clock-in')->postJson('/api/v1/time/me/time-events', $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $eventId)
        ->assertJsonPath('meta.idempotent_replay', true);
});

it('requires the personal write scope to register an event', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
    $readToken = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal-read', ['self:read'])->plainTextToken;
    $payload = ['event_type' => 'clock_out', 'occurred_at' => '2026-09-18T23:00:00Z'];

    $this->withToken($readToken)->withHeader('Idempotency-Key', 'read-only')->postJson('/api/v1/time/me/time-events', $payload)->assertForbidden();
});

it('requires an idempotency key to register a personal event', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal-write', ['self:read', 'self:write'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/time/me/time-events', [
        'event_type' => 'clock_out',
        'occurred_at' => '2026-09-18T23:00:00Z',
    ])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
});

it('does not reuse an idempotency key that belongs to another personal worker', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $other = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
    TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $other->id,
        'source' => 'pwa',
        'idempotency_key' => 'taken-personal-key',
    ]);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal-write', ['self:read', 'self:write'])->plainTextToken;

    $this->withToken($token)->withHeader('Idempotency-Key', 'taken-personal-key')->postJson('/api/v1/time/me/time-events', [
        'event_type' => 'clock_in',
        'occurred_at' => '2026-09-18T15:00:00Z',
    ])->assertUnprocessable()->assertJsonValidationErrors('event');
});

it('only exposes personal event and work-day details from the linked worker', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $other = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
    $ownEvent = TimeEvent::factory()->create(['company_id' => $company->id, 'worker_id' => $worker->id]);
    $hiddenEvent = TimeEvent::factory()->create(['company_id' => $company->id, 'worker_id' => $other->id]);
    $ownDay = WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $worker->id]);
    $hiddenDay = WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $other->id]);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal', ['self:read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/time/me/time-events/'.$ownEvent->id)
        ->assertOk()->assertJsonPath('data.id', (string) $ownEvent->id);
    $this->withToken($token)->getJson('/api/v1/time/me/work-days/'.$ownDay->id)
        ->assertOk()->assertJsonPath('data.id', (string) $ownDay->id);
    $this->withToken($token)->getJson('/api/v1/time/me/time-events/'.$hiddenEvent->id)->assertNotFound();
    $this->withToken($token)->getJson('/api/v1/time/me/work-days/'.$hiddenDay->id)->assertNotFound();
});

it('filters personal lists without allowing a worker context override', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $other = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
    $visibleEvent = TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'event_type' => 'clock_in',
        'status' => 'valid',
        'occurred_local_date' => '2026-09-16',
    ]);
    TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'event_type' => 'clock_out',
        'status' => 'valid',
        'occurred_local_date' => '2026-09-17',
    ]);
    TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $other->id,
        'event_type' => 'clock_in',
        'status' => 'valid',
        'occurred_local_date' => '2026-09-16',
    ]);
    $visibleDay = WorkDay::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-16',
        'status' => WorkDay::STATUS_CALCULATED,
    ]);
    WorkDay::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-09-17',
        'status' => WorkDay::STATUS_PENDING,
    ]);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal', ['self:read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/time/me/time-events?date_from=2026-09-16&date_to=2026-09-16&event_type=clock_in&status=valid')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $visibleEvent->id)
        ->assertJsonCount(1, 'data');
    $this->withToken($token)->getJson('/api/v1/time/me/work-days?date_from=2026-09-16&date_to=2026-09-16&status=calculated')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $visibleDay->id)
        ->assertJsonCount(1, 'data');
    $this->withToken($token)->getJson('/api/v1/time/me/time-events?event_type=invalid')->assertUnprocessable();
    $this->withToken($token)->getJson('/api/v1/time/me/time-events?worker_id='.$other->id)->assertUnprocessable();
});

it('rejects a personal token without an active worker link', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal', ['self:read'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/time/me/time-events')->assertForbidden();
});

it('blocks personal access after the worker link is revoked', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $link = UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal', ['self:read'])->plainTextToken;

    $link->update(['status' => 'revoked']);

    $this->withToken($token)->getJson('/api/v1/time/me/time-events')->assertForbidden();
});

it('blocks a personal token when its user becomes inactive', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal', ['self:read'])->plainTextToken;

    $user->update(['status' => 'inactive']);

    $this->withToken($token)->getJson('/api/v1/time/me/time-events')->assertForbidden();
});

it('blocks a personal token when VERA Time is suspended for its account', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal', ['self:read'])->plainTextToken;

    CustomerAccountProduct::query()
        ->where('customer_account_id', $account->id)
        ->update(['status' => CustomerAccountProduct::STATUS_SUSPENDED]);

    $this->withToken($token)->getJson('/api/v1/time/me/time-events')->assertForbidden();
});

it('allows a personal client to revoke only its own current token', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
    $token = app(IssueCompanyApiTokenAction::class)->handle($user, $company, 'personal', ['self:read'])->plainTextToken;
    $tokenId = (int) str($token)->before('|')->toString();

    $this->withToken($token)->deleteJson('/api/v1/time/me/access-token')->assertNoContent();
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
});
