<?php

use App\Models\Alert;
use App\Models\AlertType;
use App\Models\Center;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\WorkDay;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed(ProductSeeder::class);
});

it('authenticates a linked worker and returns a personal mobile token with its context', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id, 'timezone' => 'America/Hermosillo']);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
    $occurredAt = CarbonImmutable::now('America/Hermosillo')->subMinute();
    TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'event_type' => 'clock_in',
        'occurred_at_utc' => $occurredAt->utc(),
        'occurred_local_date' => $occurredAt->toDateString(),
        'occurred_local_time' => $occurredAt->format('H:i:s'),
        'timezone' => 'America/Hermosillo',
        'status' => 'valid',
        'voided_at' => null,
    ]);

    $response = $this->postJson('/api/v1/time/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.abilities.0', 'self:read')
        ->assertJsonPath('data.abilities.1', 'self:write')
        ->assertJsonPath('data.context.company.id', (string) $company->id)
        ->assertJsonPath('data.context.company.timezone', 'America/Hermosillo')
        ->assertJsonPath('data.context.worker.id', (string) $worker->id)
        ->assertJsonPath('data.context.permissions.can_register_time_events', true)
        ->assertJsonPath('data.context.current_time_record.state', 'trabajando')
        ->assertJsonPath('data.context.current_time_record.allowed_actions.0', 'break_start')
        ->assertJsonPath('data.context.current_time_record.allowed_actions.1', 'clock_out')
        ->assertJsonStructure(['meta' => ['trace_id']]);

    $this->withToken($response->json('data.token'))
        ->getJson('/api/v1/time/me')
        ->assertOk()
        ->assertJsonPath('data.company.id', (string) $company->id)
        ->assertJsonPath('data.worker.id', (string) $worker->id);

    $this->assertDatabaseHas('personal_access_tokens', [
        'company_id' => $company->id,
        'tokenable_id' => $user->id,
        'name' => 'pwa-personal',
    ]);
});

it('requires an explicit eligible company before issuing a mobile token to a multi-company worker', function (): void {
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $firstAccount = CustomerAccount::factory()->create();
    $secondAccount = CustomerAccount::factory()->create();
    $firstCompany = Company::factory()->create(['customer_account_id' => $firstAccount->id]);
    $secondCompany = Company::factory()->create(['customer_account_id' => $secondAccount->id]);
    $firstWorker = Worker::factory()->create(['company_id' => $firstCompany->id]);
    $secondWorker = Worker::factory()->create(['company_id' => $secondCompany->id]);
    UserWorkerLink::create(['company_id' => $firstCompany->id, 'user_id' => $user->id, 'worker_id' => $firstWorker->id, 'status' => 'active']);
    UserWorkerLink::create(['company_id' => $secondCompany->id, 'user_id' => $user->id, 'worker_id' => $secondWorker->id, 'status' => 'active']);

    $this->postJson('/api/v1/time/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertConflict()
        ->assertJsonPath('code', 'company_selection_required')
        ->assertJsonCount(2, 'data.companies');

    $this->assertDatabaseCount('personal_access_tokens', 0);

    $this->postJson('/api/v1/time/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'company_id' => $secondCompany->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.context.company.id', (string) $secondCompany->id)
        ->assertJsonPath('data.context.worker.id', (string) $secondWorker->id);
});

it('does not issue a mobile token when the credentials or personal worker link are invalid', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);

    $this->postJson('/api/v1/time/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/time/auth/login', [
        'email' => $user->email,
        'password' => 'password',
        'company_id' => $company->id,
    ])->assertForbidden();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('requests password recovery without revealing whether the mobile account exists', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/api/v1/time/auth/forgot-password', ['email' => $user->email])
        ->assertAccepted()
        ->assertJsonPath('message', 'Si la cuenta existe, se enviaron instrucciones para restablecer la contraseña.');
    Notification::assertSentTo($user, ResetPassword::class);

    $this->postJson('/api/v1/time/auth/forgot-password', ['email' => 'no-existe@veratime.test'])
        ->assertAccepted()
        ->assertJsonPath('message', 'Si la cuenta existe, se enviaron instrucciones para restablecer la contraseña.');
});

it('sends a mobile password recovery through Brevo HTTPS when configured', function (): void {
    config()->set('services.brevo', [
        'enabled' => true,
        'api_key' => 'brevo-test-key',
        'endpoint' => 'https://api.brevo.test/v3/smtp/email',
        'timeout' => 10,
        'sender' => ['address' => 'soporte@gotvera.test', 'name' => 'VERA Time'],
        'contact_recipient' => 'soporte@gotvera.test',
    ]);
    Http::fake([
        'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'test-message-id'], 201),
    ]);
    $user = User::factory()->create();

    $this->postJson('/api/v1/time/auth/forgot-password', ['email' => $user->email])
        ->assertAccepted();

    Http::assertSent(function (Request $request) use ($user): bool {
        $payload = $request->data();

        return $request->url() === 'https://api.brevo.test/v3/smtp/email'
            && data_get($payload, 'to.0.email') === $user->email
            && data_get($payload, 'tags.0') === 'password-reset';
    });
});

it('syncs queued personal events idempotently without allowing a worker override', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $otherWorker = Worker::factory()->create(['company_id' => $company->id]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);
    $login = $this->postJson('/api/v1/time/auth/login', ['email' => $user->email, 'password' => 'password']);
    $payload = [
        'events' => [
            ['client_event_id' => 'offline-clock-in-001', 'event_type' => 'clock_in', 'occurred_at' => '2026-09-20T15:00:00Z', 'device' => ['code' => 'phone-01']],
            ['client_event_id' => 'offline-clock-out-001', 'event_type' => 'clock_out', 'occurred_at' => '2026-09-20T23:00:00Z'],
        ],
    ];

    $this->withToken($login->json('data.token'))
        ->postJson('/api/v1/time/me/time-events/sync', ['events' => [[...$payload['events'][0], 'worker_id' => $otherWorker->id]]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('events.0.worker_id');

    $this->withToken($login->json('data.token'))
        ->postJson('/api/v1/time/me/time-events/sync', $payload)
        ->assertOk()
        ->assertJsonPath('data.0.status', 'accepted')
        ->assertJsonPath('data.1.status', 'accepted')
        ->assertJsonPath('meta.accepted', 2)
        ->assertJsonPath('meta.rejected', 0);

    $this->withToken($login->json('data.token'))
        ->postJson('/api/v1/time/me/time-events/sync', $payload)
        ->assertOk()
        ->assertJsonPath('data.0.status', 'already_registered')
        ->assertJsonPath('data.1.status', 'already_registered')
        ->assertJsonPath('meta.already_registered', 2);

    $this->assertDatabaseCount('time_events', 2);
    $this->assertDatabaseHas('time_events', ['company_id' => $company->id, 'worker_id' => $worker->id, 'idempotency_key' => 'offline-clock-in-001', 'source' => 'pwa']);
});

it('only returns published personal schedules for the linked workers active relationship', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $otherWorker = Worker::factory()->create(['company_id' => $company->id]);
    $center = Center::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'status' => 'active',
    ]);
    $otherRelationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $otherWorker->id,
        'center_id' => $center->id,
        'status' => 'active',
    ]);
    $batch = ScheduleBatch::factory()->create([
        'company_id' => $company->id,
        'center_id' => $center->id,
        'status' => 'published',
    ]);
    $ownSchedule = DailyScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_batch_id' => $batch->id,
        'employment_relationship_id' => $relationship->id,
        'work_date' => '2026-09-21',
        'day_type' => 'shift',
        'timezone' => $company->timezone,
        'required_minutes' => 480,
    ]);
    DailyScheduleSegment::factory()->create([
        'company_id' => $company->id,
        'daily_schedule_assignment_id' => $ownSchedule->id,
        'segment_order' => 1,
        'segment_type' => 'work',
        'start_local_time' => '08:00:00',
        'end_local_time' => '16:00:00',
    ]);
    $hiddenSchedule = DailyScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_batch_id' => $batch->id,
        'employment_relationship_id' => $otherRelationship->id,
        'work_date' => '2026-09-21',
        'day_type' => 'shift',
        'timezone' => $company->timezone,
    ]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);

    $login = $this->postJson('/api/v1/time/auth/login', ['email' => $user->email, 'password' => 'password']);

    $this->withToken($login->json('data.token'))
        ->getJson('/api/v1/time/me/schedule?date_from=2026-09-21&date_to=2026-09-21')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $ownSchedule->id)
        ->assertJsonPath('data.0.required_minutes', 480)
        ->assertJsonPath('data.0.center.id', (string) $center->id)
        ->assertJsonPath('data.0.segments.0.type', 'work')
        ->assertJsonMissing(['id' => (string) $hiddenSchedule->id]);

    $this->withToken($login->json('data.token'))
        ->getJson('/api/v1/time/me/schedule?worker_id='.$otherWorker->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('worker_id');

    $this->withToken($login->json('data.token'))
        ->getJson('/api/v1/time/me/schedule?date_from=2026-09-01&date_to=2026-10-03')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_to');
});

it('only returns alerts belonging to the linked worker', function (): void {
    $account = CustomerAccount::factory()->create();
    $company = Company::factory()->create(['customer_account_id' => $account->id]);
    $user = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $otherWorker = Worker::factory()->create(['company_id' => $company->id]);
    $type = AlertType::query()->create([
        'code' => 'personal_alert_'.str()->random(8),
        'name' => 'Situación pendiente de revisión',
        'default_severity' => AlertType::SEVERITY_WARNING,
        'category' => 'attendance',
        'status' => AlertType::STATUS_ACTIVE,
    ]);
    $ownDay = WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $worker->id]);
    $otherDay = WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $otherWorker->id]);
    $ownAlert = Alert::query()->create([
        'company_id' => $company->id,
        'alert_type_id' => $type->id,
        'worker_id' => $worker->id,
        'work_day_id' => $ownDay->id,
        'severity' => AlertType::SEVERITY_WARNING,
        'status' => Alert::STATUS_NEW,
        'title' => 'Situación propia',
        'detected_at' => now('UTC'),
        'fingerprint' => 'personal-alert-own-'.$worker->id,
    ]);
    Alert::query()->create([
        'company_id' => $company->id,
        'alert_type_id' => $type->id,
        'worker_id' => $otherWorker->id,
        'work_day_id' => $otherDay->id,
        'severity' => AlertType::SEVERITY_WARNING,
        'status' => Alert::STATUS_NEW,
        'title' => 'Situación ajena',
        'detected_at' => now('UTC'),
        'fingerprint' => 'personal-alert-hidden-'.$otherWorker->id,
    ]);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);

    $login = $this->postJson('/api/v1/time/auth/login', ['email' => $user->email, 'password' => 'password']);

    $this->withToken($login->json('data.token'))
        ->getJson('/api/v1/time/me/alerts?worker_id='.$otherWorker->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('worker_id');

    $this->withToken($login->json('data.token'))
        ->getJson('/api/v1/time/me/alerts')
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $ownAlert->id)
        ->assertJsonCount(1, 'data');
});
