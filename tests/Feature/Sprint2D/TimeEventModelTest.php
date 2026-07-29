<?php

use App\Domains\TimeRecords\Actions\CreateTimeEventAction;
use App\Domains\TimeRecords\Actions\ResolveCurrentTimeRecordStateAction;
use App\Domains\TimeRecords\Actions\ResolveValidTimeEventsForWorkDateAction;
use App\Domains\TimeRecords\Actions\VoidTimeEventAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

it('creates a valid time event from domain action', function (): void {
    [$company, $worker, $relationship, $center, $sourceUser] = timeEventFixture();

    $event = app(CreateTimeEventAction::class)->handle($company, $worker, [
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-14',
        'occurred_local_time' => '22:30:00',
        'timezone' => 'America/Mexico_City',
        'received_at' => '2026-08-15 04:31:00',
        'source' => 'web',
        'metadata' => ['ip' => '127.0.0.1'],
    ], $relationship, $center, $sourceUser);

    expect($event)->toBeInstanceOf(TimeEvent::class)
        ->and($event->company->is($company))->toBeTrue()
        ->and($event->worker->is($worker))->toBeTrue()
        ->and($event->employmentRelationship->is($relationship))->toBeTrue()
        ->and($event->center->is($center))->toBeTrue()
        ->and($event->sourceUser->is($sourceUser))->toBeTrue()
        ->and($event->event_type)->toBe('clock_in')
        ->and($event->source)->toBe('web')
        ->and($event->status)->toBe('valid')
        ->and($event->metadata)->toBe(['ip' => '127.0.0.1']);

    $this->assertDatabaseHas('time_events', [
        'id' => $event->id,
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'employment_relationship_id' => $relationship->id,
        'center_id' => $center->id,
        'source_user_id' => $sourceUser->id,
        'event_type' => 'clock_in',
        'source' => 'web',
        'status' => 'valid',
    ]);
});

it('supports nullable employment relationship center device source user and json metadata', function (): void {
    $company = Company::factory()->create();
    $worker = Worker::factory()->create(['company_id' => $company->id]);

    $event = app(CreateTimeEventAction::class)->handle($company, $worker, [
        'event_type' => 'clock_out',
        'occurred_at_utc' => '2026-08-15 04:30:00',
        'timezone' => 'America/Mexico_City',
        'received_at' => '2026-08-15 04:31:00',
        'source' => 'job',
        'metadata' => ['source_batch' => 'nightly'],
    ]);

    expect($event->employment_relationship_id)->toBeNull()
        ->and($event->center_id)->toBeNull()
        ->and($event->device_id)->toBeNull()
        ->and($event->source_user_id)->toBeNull()
        ->and($event->metadata)->toBe(['source_batch' => 'nightly']);
});

it('accepts only controlled event types sources and statuses', function (): void {
    [$company, $worker] = timeEventFixture();
    $action = app(CreateTimeEventAction::class);

    foreach (TimeEvent::EVENT_TYPES as $eventType) {
        $event = $action->handle($company, $worker, timeEventPayload([
            'event_type' => $eventType,
            'idempotency_key' => 'type-'.$eventType,
        ]));

        expect($event->event_type)->toBe($eventType);
    }

    foreach (TimeEvent::SOURCES as $source) {
        $event = $action->handle($company, $worker, timeEventPayload([
            'source' => $source,
            'idempotency_key' => 'source-'.$source,
        ]));

        expect($event->source)->toBe($source);
    }

    foreach (TimeEvent::STATUSES as $status) {
        $event = $action->handle($company, $worker, timeEventPayload([
            'status' => $status,
            'idempotency_key' => 'status-'.$status,
        ]));

        expect($event->status)->toBe($status);
    }

    expect(fn () => $action->handle($company, $worker, timeEventPayload(['event_type' => 'lunch'])))->toThrow(InvalidArgumentException::class);
    expect(fn () => $action->handle($company, $worker, timeEventPayload(['source' => 'redis'])))->toThrow(InvalidArgumentException::class);
    expect(fn () => $action->handle($company, $worker, timeEventPayload(['status' => 'deleted'])))->toThrow(InvalidArgumentException::class);
});

it('defaults admin manual source to pending review and other sources to valid', function (): void {
    [$company, $worker] = timeEventFixture();
    $action = app(CreateTimeEventAction::class);

    $manual = $action->handle($company, $worker, timeEventPayload([
        'source' => 'admin_manual',
        'idempotency_key' => 'manual-default',
    ]));
    $web = $action->handle($company, $worker, timeEventPayload([
        'source' => 'web',
        'idempotency_key' => 'web-default',
    ]));

    expect($manual->status)->toBe('pending_review')
        ->and($web->status)->toBe('valid');
});

it('blocks worker employment relationship center and source user from another company', function (): void {
    [$company, $worker, $relationship, $center, $sourceUser] = timeEventFixture();
    [$otherCompany, $otherWorker, $otherRelationship, $otherCenter, $otherUser] = timeEventFixture();
    $action = app(CreateTimeEventAction::class);

    expect(fn () => $action->handle($company, $otherWorker, timeEventPayload()))->toThrow(InvalidArgumentException::class);
    expect(fn () => $action->handle($company, $worker, timeEventPayload(), $otherRelationship))->toThrow(InvalidArgumentException::class);
    expect(fn () => $action->handle($company, $worker, timeEventPayload(), $relationship, $otherCenter))->toThrow(InvalidArgumentException::class);
    expect(fn () => $action->handle($company, $worker, timeEventPayload(), $relationship, $center, $otherUser))->toThrow(InvalidArgumentException::class);
});

it('blocks employment relationship from same company but another worker', function (): void {
    [$company, $worker] = timeEventFixture();
    $otherWorker = Worker::factory()->create(['company_id' => $company->id]);
    $otherRelationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $otherWorker->id,
    ]);

    expect(fn () => app(CreateTimeEventAction::class)->handle($company, $worker, timeEventPayload(), $otherRelationship))
        ->toThrow(InvalidArgumentException::class);
});

it('ignores manipulated company id and uses explicit active company', function (): void {
    [$company, $worker] = timeEventFixture();
    $otherCompany = Company::factory()->create();

    $event = app(CreateTimeEventAction::class)->handle($company, $worker, timeEventPayload([
        'company_id' => $otherCompany->id,
    ]));

    expect($event->company_id)->toBe($company->id)
        ->and($event->company_id)->not->toBe($otherCompany->id);
});

it('blocks inactive company from creating time events', function (): void {
    $company = Company::factory()->create(['status' => 'inactive']);
    $worker = Worker::factory()->create(['company_id' => $company->id]);

    expect(fn () => app(CreateTimeEventAction::class)->handle($company, $worker, timeEventPayload()))
        ->toThrow(InvalidArgumentException::class);
});


it('keeps occurred local time as h i s operational string', function (): void {
    [$company, $worker] = timeEventFixture();

    $event = app(CreateTimeEventAction::class)->handle($company, $worker, timeEventPayload([
        'occurred_local_date' => '2026-08-14',
        'occurred_local_time' => '08:05:07',
        'timezone' => 'America/Mexico_City',
    ]));

    expect($event->occurred_local_time)->toBe('08:05:07')
        ->and($event->occurred_local_time)->toMatch('/^\d{2}:\d{2}:\d{2}$/');
});

it('converts local date time and timezone to utc', function (): void {
    [$company, $worker] = timeEventFixture();

    $event = app(CreateTimeEventAction::class)->handle($company, $worker, timeEventPayload([
        'occurred_local_date' => '2026-08-14',
        'occurred_local_time' => '22:30:00',
        'timezone' => 'America/Mexico_City',
    ]));

    expect($event->occurred_local_date->toDateString())->toBe('2026-08-14')
        ->and($event->occurred_local_time)->toBe('22:30:00')
        ->and($event->timezone)->toBe('America/Mexico_City')
        ->and($event->occurred_at_utc->utc()->format('Y-m-d H:i:s'))->toBe('2026-08-15 04:30:00');
});

it('converts utc to local date and handles overnight local date', function (): void {
    [$company, $worker] = timeEventFixture();

    $event = app(CreateTimeEventAction::class)->handle($company, $worker, [
        'event_type' => 'clock_out',
        'occurred_at_utc' => '2026-08-15 05:30:00',
        'timezone' => 'America/Mexico_City',
        'received_at' => '2026-08-15 05:31:00',
        'source' => 'web',
    ]);

    expect($event->occurred_at_utc->utc()->format('Y-m-d H:i:s'))->toBe('2026-08-15 05:30:00')
        ->and($event->occurred_local_date->toDateString())->toBe('2026-08-14')
        ->and($event->occurred_local_time)->toBe('23:30:00');
});

it('uses center timezone when timezone is not provided', function (): void {
    [$company, $worker, $relationship, $center] = timeEventFixture(centerAttributes: ['timezone' => 'America/Tijuana']);

    $event = app(CreateTimeEventAction::class)->handle($company, $worker, [
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-14',
        'occurred_local_time' => '22:30:00',
        'received_at' => '2026-08-15 05:31:00',
        'source' => 'web',
    ], $relationship, $center);

    expect($event->timezone)->toBe('America/Tijuana')
        ->and($event->occurred_at_utc->utc()->format('Y-m-d H:i:s'))->toBe('2026-08-15 05:30:00');
});

it('prevents duplicate idempotency key in same company and allows it in another company', function (): void {
    [$company, $worker] = timeEventFixture();
    [$otherCompany, $otherWorker] = timeEventFixture();
    $action = app(CreateTimeEventAction::class);

    $first = $action->handle($company, $worker, timeEventPayload(['idempotency_key' => 'same-key']));
    $second = $action->handle($company, $worker, timeEventPayload([
        'event_type' => 'clock_out',
        'idempotency_key' => 'same-key',
    ]));
    $third = $action->handle($otherCompany, $otherWorker, timeEventPayload(['idempotency_key' => 'same-key']));

    expect($second->id)->toBe($first->id)
        ->and($third->id)->not->toBe($first->id);

    expect(TimeEvent::query()->where('idempotency_key', 'same-key')->count())->toBe(2);
});

it('prevents duplicate external id and source in same company and allows it in another company', function (): void {
    [$company, $worker] = timeEventFixture();
    [$otherCompany, $otherWorker] = timeEventFixture();
    $action = app(CreateTimeEventAction::class);

    $first = $action->handle($company, $worker, timeEventPayload([
        'source' => 'api',
        'external_id' => 'EXT-001',
    ]));
    $second = $action->handle($company, $worker, timeEventPayload([
        'source' => 'api',
        'external_id' => 'EXT-001',
        'event_type' => 'clock_out',
    ]));
    $differentSource = $action->handle($company, $worker, timeEventPayload([
        'source' => 'csv',
        'external_id' => 'EXT-001',
    ]));
    $third = $action->handle($otherCompany, $otherWorker, timeEventPayload([
        'source' => 'api',
        'external_id' => 'EXT-001',
    ]));

    expect($second->id)->toBe($first->id)
        ->and($differentSource->id)->not->toBe($first->id)
        ->and($third->id)->not->toBe($first->id);
});

it('allows multiple events with null idempotency key or external id', function (): void {
    [$company, $worker] = timeEventFixture();
    $action = app(CreateTimeEventAction::class);

    $first = $action->handle($company, $worker, timeEventPayload([
        'idempotency_key' => null,
        'external_id' => null,
    ]));
    $second = $action->handle($company, $worker, timeEventPayload([
        'idempotency_key' => null,
        'external_id' => null,
        'event_type' => 'clock_out',
    ]));

    expect($second->id)->not->toBe($first->id);
});

it('preserves time events by blocking hard deletes through foreign keys', function (): void {
    [$company, $worker, $relationship, $center, $sourceUser] = timeEventFixture();
    $event = app(CreateTimeEventAction::class)->handle($company, $worker, timeEventPayload(), $relationship, $center, $sourceUser);

    expect(fn () => $worker->delete())->toThrow(QueryException::class);
    expect(fn () => $relationship->delete())->toThrow(QueryException::class);
    expect(fn () => $center->delete())->toThrow(QueryException::class);
    expect(fn () => $sourceUser->delete())->toThrow(QueryException::class);
    expect(fn () => $company->delete())->toThrow(QueryException::class);

    $this->assertDatabaseHas('time_events', ['id' => $event->id]);
});

it('policy blocks inactive company unauthorized roles and horizontal access', function (): void {
    [$company, $worker] = timeEventFixture();
    [$otherCompany] = timeEventFixture();
    $owner = timeEventUserWithCompany($company, 'owner');
    $rh = timeEventUserWithCompany($company, 'rh');
    $supervisor = timeEventUserWithCompany($company, 'supervisor');
    $foreignOwner = timeEventUserWithCompany($otherCompany, 'owner');
    $event = app(CreateTimeEventAction::class)->handle($company, $worker, timeEventPayload());

    expect(Gate::forUser($owner)->allows('create', [TimeEvent::class, $company]))->toBeTrue()
        ->and(Gate::forUser($rh)->allows('create', [TimeEvent::class, $company]))->toBeTrue()
        ->and(Gate::forUser($supervisor)->allows('create', [TimeEvent::class, $company]))->toBeFalse()
        ->and(Gate::forUser($foreignOwner)->allows('view', $event))->toBeFalse();

    $company->update(['status' => 'inactive']);

    expect(Gate::forUser($owner)->allows('view', $event->refresh()))->toBeFalse();
});

it('voids a time event logically with required reason actor timestamp and resulting status', function (): void {
    [$company, $worker, $relationship, $center, $sourceUser] = timeEventFixture();
    $event = app(CreateTimeEventAction::class)->handle($company, $worker, timeEventPayload([
        'source' => 'web',
        'metadata' => ['terminal' => 'front-desk'],
    ]), $relationship, $center, $sourceUser);
    $actor = timeEventUserWithCompany($company, 'rh');
    $voidedAt = CarbonImmutable::parse('2026-08-14 20:00:00', 'UTC');

    $voided = app(VoidTimeEventAction::class)->handle($event, $actor, 'Registro duplicado por error operativo', $voidedAt);

    expect($voided->id)->toBe($event->id)
        ->and($voided->status)->toBe('voided')
        ->and($voided->void_reason)->toBe('Registro duplicado por error operativo')
        ->and($voided->voided_by_user_id)->toBe($actor->id)
        ->and($voided->voided_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-08-14 20:00:00')
        ->and($voided->event_type)->toBe($event->event_type)
        ->and($voided->occurred_at_utc->equalTo($event->occurred_at_utc))->toBeTrue()
        ->and($voided->source)->toBe('web')
        ->and($voided->metadata['terminal'])->toBe('front-desk')
        ->and($voided->metadata['void']['actor_user_id'])->toBe($actor->id)
        ->and($voided->metadata['void']['resulting_status'])->toBe('voided')
        ->and($voided->metadata['void']['previous_status'])->toBe('valid');

    $this->assertDatabaseCount('time_events', 1);
});

it('blocks a second logical void for the same time event', function (): void {
    [$company, $worker, $relationship, $center, $sourceUser] = timeEventFixture();
    $actor = timeEventUserWithCompany($company, 'admin');
    $event = app(CreateTimeEventAction::class)->handle($company, $worker, timeEventPayload(), $relationship, $center, $sourceUser);

    app(VoidTimeEventAction::class)->handle($event, $actor, 'Primera anulacion valida');

    expect(fn () => app(VoidTimeEventAction::class)->handle($event->refresh(), $actor, 'Segunda anulacion'))
        ->toThrow(AuthorizationException::class);
});

it('excludes voided events from valid event resolution and current state', function (): void {
    [$company, $worker, $relationship, $center, $sourceUser] = timeEventFixture();
    $actor = timeEventUserWithCompany($company, 'owner');
    $event = app(CreateTimeEventAction::class)->handle($company, $worker, timeEventPayload([
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-14',
        'occurred_local_time' => '08:00:00',
    ]), $relationship, $center, $sourceUser);

    app(VoidTimeEventAction::class)->handle($event, $actor, 'Entrada registrada dos veces');

    $validEvents = app(ResolveValidTimeEventsForWorkDateAction::class)->handle($company, $relationship, '2026-08-14');
    $state = app(ResolveCurrentTimeRecordStateAction::class)->handle(
        $company,
        $worker,
        CarbonImmutable::parse('2026-08-14 15:00:00', 'UTC'),
        $center,
    );

    expect($validEvents)->toHaveCount(0)
        ->and($state['state'])->toBe('sin_entrada')
        ->and($state['last_event'])->toBeNull();
});

it('keeps late event occurrence and received timestamps as different evidence points', function (): void {
    [$company, $worker, $relationship, $center, $sourceUser] = timeEventFixture();

    $event = app(CreateTimeEventAction::class)->handle($company, $worker, timeEventPayload([
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-14',
        'occurred_local_time' => '08:00:00',
        'received_at' => '2026-08-14 18:35:00',
        'source' => 'admin_manual',
        'metadata' => ['reason' => 'Registro tardio justificado'],
    ]), $relationship, $center, $sourceUser);

    expect($event->occurred_at_utc->utc()->format('Y-m-d H:i:s'))->toBe('2026-08-14 14:00:00')
        ->and($event->received_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-08-14 18:35:00')
        ->and($event->received_at->greaterThan($event->occurred_at_utc))->toBeTrue()
        ->and($event->metadata['reason'])->toBe('Registro tardio justificado');
});

it('resolves out of order events by occurrence time instead of insertion order', function (): void {
    [$company, $worker, $relationship, $center, $sourceUser] = timeEventFixture();
    $action = app(CreateTimeEventAction::class);

    $action->handle($company, $worker, timeEventPayload([
        'event_type' => 'clock_out',
        'occurred_local_date' => '2026-08-14',
        'occurred_local_time' => '17:00:00',
        'received_at' => '2026-08-14 23:01:00',
        'idempotency_key' => 'late-clock-out',
    ]), $relationship, $center, $sourceUser);
    $action->handle($company, $worker, timeEventPayload([
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-14',
        'occurred_local_time' => '08:00:00',
        'received_at' => '2026-08-14 23:02:00',
        'idempotency_key' => 'late-clock-in',
    ]), $relationship, $center, $sourceUser);

    $events = app(ResolveValidTimeEventsForWorkDateAction::class)->handle($company, $relationship, '2026-08-14');

    expect($events->pluck('event_type')->all())->toBe(['clock_in', 'clock_out']);
});

it('uses deterministic event type precedence when occurrence and received timestamps tie', function (): void {
    [$company, $worker, $relationship, $center, $sourceUser] = timeEventFixture();
    $action = app(CreateTimeEventAction::class);

    foreach (['clock_out', 'break_end', 'break_start', 'clock_in'] as $eventType) {
        $action->handle($company, $worker, timeEventPayload([
            'event_type' => $eventType,
            'occurred_local_date' => '2026-08-14',
            'occurred_local_time' => '08:00:00',
            'received_at' => '2026-08-14 14:00:00',
            'idempotency_key' => 'tie-'.$eventType,
        ]), $relationship, $center, $sourceUser);
    }

    $events = app(ResolveValidTimeEventsForWorkDateAction::class)->handle($company, $relationship, '2026-08-14');

    expect($events->pluck('event_type')->all())->toBe(['clock_in', 'break_start', 'break_end', 'clock_out']);
});

it('void permissions allow owner admin and rh but block supervisor foreign and inactive memberships', function (): void {
    [$company, $worker, $relationship, $center, $sourceUser] = timeEventFixture();
    [$otherCompany] = timeEventFixture();
    $event = app(CreateTimeEventAction::class)->handle($company, $worker, timeEventPayload(), $relationship, $center, $sourceUser);
    $owner = timeEventUserWithCompany($company, 'owner');
    $admin = timeEventUserWithCompany($company, 'admin');
    $rh = timeEventUserWithCompany($company, 'rh');
    $supervisor = timeEventUserWithCompany($company, 'supervisor');
    $foreignOwner = timeEventUserWithCompany($otherCompany, 'owner');
    $inactiveMember = timeEventUserWithCompany($company, 'owner', membershipStatus: 'inactive');

    expect(Gate::forUser($owner)->allows('void', $event))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('void', $event))->toBeTrue()
        ->and(Gate::forUser($rh)->allows('void', $event))->toBeTrue()
        ->and(Gate::forUser($supervisor)->allows('void', $event))->toBeFalse()
        ->and(Gate::forUser($foreignOwner)->allows('void', $event))->toBeFalse()
        ->and(Gate::forUser($inactiveMember)->allows('void', $event))->toBeFalse();
});

it('sprint 2d creates only time events and no future operational modules', function (): void {
    expect(Schema::hasTable('time_events'))->toBeTrue()
        ->and(Schema::hasTable('work_days'))->toBeFalse()
        ->and(Schema::hasTable('work_day_calculations'))->toBeFalse()
        ->and(Schema::hasTable('alerts'))->toBeFalse()
        ->and(Schema::hasTable('incidents'))->toBeFalse()
        ->and(Schema::hasTable('reports'))->toBeFalse()
        ->and(Schema::hasTable('kiosk_sessions'))->toBeFalse();
});

function timeEventPayload(array $overrides = []): array
{
    return array_replace([
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-14',
        'occurred_local_time' => '08:30:00',
        'timezone' => 'America/Mexico_City',
        'received_at' => '2026-08-14 14:31:00',
        'source' => 'web',
    ], $overrides);
}

/**
 * @return array{0: Company, 1: Worker, 2: EmploymentRelationship, 3: Center, 4: User}
 */
function timeEventFixture(array $centerAttributes = []): array
{
    $company = Company::factory()->create();
    $center = Center::factory()->create(array_replace([
        'company_id' => $company->id,
        'timezone' => 'America/Mexico_City',
    ], $centerAttributes));
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
    ]);
    $sourceUser = timeEventUserWithCompany($company);

    return [$company, $worker, $relationship, $center, $sourceUser];
}

function timeEventUserWithCompany(Company $company, string $roleKey = 'owner', string $membershipStatus = 'active'): User
{
    $role = Role::query()->firstOrCreate(
        ['key' => $roleKey],
        ['name' => $roleKey, 'description' => null, 'is_system' => true]
    );
    $user = User::factory()->create();

    $user->companies()->attach($company, [
        'role_id' => $role->id,
        'status' => $membershipStatus,
        'is_default' => true,
    ]);

    return $user;
}
