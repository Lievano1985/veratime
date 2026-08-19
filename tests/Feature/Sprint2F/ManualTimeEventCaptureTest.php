<?php

use App\Domains\TimeRecords\Actions\RegisterManualTimeEventAction;
use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-17 15:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('guest cannot access manual capture route', function (): void {
    $this->get(route('time-events.manual'))
        ->assertRedirect(route('login'));
});

it('unauthorized role cannot access manual capture route', function (): void {
    [$company] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, 'supervisor');

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->assertForbidden();
});

it('authorized role sees manual capture screen', function (): void {
    [$company, $worker] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->assertSee('Eventos')
        ->assertSee($worker->full_name);
});

it('manual selector only shows active company workers', function (): void {
    [$company, $worker] = sprint2fManualFixture();
    [, $otherWorker] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->assertSee($worker->full_name)
        ->assertDontSee($otherWorker->full_name);
});

it('rh operativo can capture and list events only for assigned center', function (): void {
    [$company, $worker, $relationship, $center] = sprint2fManualFixture(workerAttributes: ['full_name' => 'Ana Centro Permitido']);
    [, $otherWorker, $otherRelationship, $otherCenter] = sprint2fManualFixture(workerAttributes: ['full_name' => 'Bruno Otro Centro']);
    $otherWorker->forceFill(['company_id' => $company->id])->save();
    $otherCenter->forceFill(['company_id' => $company->id])->save();
    $otherRelationship->forceFill([
        'company_id' => $company->id,
        'worker_id' => $otherWorker->id,
        'center_id' => $otherCenter->id,
    ])->save();

    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_OPERATIVO);
    app(AssignOperationalScopeAction::class)->handle($company, $user, [
        'effective_from' => '2026-08-01',
        'reason' => 'RH operativo por centro completo',
    ], center: $center);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->assertSee('Ana Centro Permitido')
        ->assertDontSee('Bruno Otro Centro')
        ->set('workerId', (string) $worker->id)
        ->set('eventType', 'clock_in')
        ->set('occurredLocalDate', '2026-08-16')
        ->set('occurredLocalTime', '08:05')
        ->set('reason', 'Captura justificada por RH operativo')
        ->call('capture')
        ->assertHasNoErrors()
        ->assertSee('Captura justificada guardada')
        ->set('workerId', (string) $otherWorker->id)
        ->set('eventType', 'clock_out')
        ->set('occurredLocalDate', '2026-08-16')
        ->set('occurredLocalTime', '17:05')
        ->set('reason', 'Intento fuera de alcance')
        ->call('capture')
        ->assertHasErrors(['workerId']);

    $this->assertDatabaseHas('time_events', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'source' => 'admin_manual',
    ]);
    $this->assertDatabaseMissing('time_events', [
        'company_id' => $company->id,
        'worker_id' => $otherWorker->id,
        'source' => 'admin_manual',
    ]);
});

it('manual capture validates required fields and invalid type', function (): void {
    [$company] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->set('workerId', '')
        ->set('eventType', '')
        ->set('occurredLocalDate', '')
        ->set('occurredLocalTime', '')
        ->set('reason', '')
        ->call('capture')
        ->assertHasErrors(['workerId', 'eventType', 'occurredLocalDate', 'occurredLocalTime', 'reason']);

    Volt::test('time-events.manual')
        ->set('eventType', 'manual_entry')
        ->call('capture')
        ->assertHasErrors(['eventType']);
});

it('manual capture does not allow worker from another company', function (): void {
    [$company] = sprint2fManualFixture();
    [, $otherWorker] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->set('workerId', (string) $otherWorker->id)
        ->set('eventType', 'clock_in')
        ->set('occurredLocalDate', '2026-08-16')
        ->set('occurredLocalTime', '08:05')
        ->set('reason', 'Olvido registrar entrada')
        ->call('capture')
        ->assertHasErrors(['workerId']);
});

it('manual action creates valid justified event with source user reason and local utc conversion', function (): void {
    [$company, $worker, $relationship, $center] = sprint2fManualFixture(centerAttributes: ['timezone' => 'America/Tijuana']);
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);

    $event = app(RegisterManualTimeEventAction::class)->handle($company, $user, $worker, [
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '08:05',
        'reason' => 'Olvido registrar entrada',
    ]);

    expect($event->event_type)->toBe('clock_in')
        ->and($event->source)->toBe('admin_manual')
        ->and($event->status)->toBe('valid')
        ->and($event->company_id)->toBe($company->id)
        ->and($event->worker_id)->toBe($worker->id)
        ->and($event->employment_relationship_id)->toBe($relationship->id)
        ->and($event->center_id)->toBe($center->id)
        ->and($event->source_user_id)->toBe($user->id)
        ->and($event->timezone)->toBe('America/Tijuana')
        ->and($event->occurred_local_date->toDateString())->toBe('2026-08-16')
        ->and($event->occurred_local_time)->toBe('08:05:00')
        ->and($event->occurred_at_utc->utc()->format('Y-m-d H:i:s'))->toBe('2026-08-16 15:05:00')
        ->and($event->received_at)->not->toBeNull()
        ->and($event->metadata['channel'])->toBe('manual')
        ->and($event->metadata['reason'])->toBe('Olvido registrar entrada')
        ->and($event->metadata['captured_by'])->toBe($user->id)
        ->and($event->metadata['context'])->toBe('manual_justified_entry')
        ->and($event->metadata['review']['decision'])->toBe('auto_approved')
        ->and($event->metadata['review']['actor_user_id'])->toBe($user->id)
        ->and($event->metadata['review']['resulting_status'])->toBe('valid');
});

it('manual livewire creates event and lists only active company manual captures', function (): void {
    [$company, $worker] = sprint2fManualFixture();
    [$otherCompany, $otherWorker] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);
    $otherUser = sprint2fManualUserWithCompany($otherCompany, RoleKey::RH_ADMIN);

    app(RegisterManualTimeEventAction::class)->handle($otherCompany, $otherUser, $otherWorker, [
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '08:00',
        'reason' => 'Otro registro manual',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->set('workerId', (string) $worker->id)
        ->set('eventType', 'clock_out')
        ->set('occurredLocalDate', '2026-08-16')
        ->set('occurredLocalTime', '17:10')
        ->set('reason', 'Salida registrada por RH')
        ->call('capture')
        ->assertHasNoErrors()
        ->assertSee('Captura justificada guardada y enviada a recalculo de jornada.')
        ->assertSee($worker->full_name)
        ->assertDontSee($otherWorker->full_name);

    $this->assertDatabaseHas('time_events', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'source' => 'admin_manual',
        'event_type' => 'clock_out',
        'status' => 'valid',
    ]);
});

it('manual livewire paginates events and filters by source and status', function (): void {
    [$company, $worker, $relationship, $center] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);

    foreach (range(1, 12) as $index) {
        TimeEvent::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $center->id,
            'event_type' => 'clock_in',
            'occurred_at_utc' => CarbonImmutable::parse("2026-08-16 08:{$index}:00", 'UTC'),
            'occurred_local_date' => '2026-08-16',
            'occurred_local_time' => sprintf('08:%02d:00', $index),
            'source' => $index <= 6 ? 'web' : 'kiosk',
            'status' => $index % 2 === 0 ? 'voided' : 'valid',
            'metadata' => [],
        ]);
    }

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->assertViewHas('events', fn ($events) => $events->total() === 12 && $events->perPage() === 10)
        ->set('sourceFilter', 'kiosk')
        ->assertViewHas('events', fn ($events) => $events->total() === 6)
        ->set('statusFilter', 'valid')
        ->assertViewHas('events', fn ($events) => $events->total() === 3);
});

it('manual livewire voids a visible company event with required reason', function (): void {
    [$company, $worker, $relationship, $center] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);
    $event = TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'employment_relationship_id' => $relationship->id,
        'center_id' => $center->id,
        'event_type' => 'clock_in',
        'occurred_at_utc' => '2026-08-16 14:00:00',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '08:00:00',
        'timezone' => 'America/Mexico_City',
        'received_at' => '2026-08-16 14:01:00',
        'source' => 'web',
        'status' => 'valid',
        'metadata' => [],
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->call('startVoid', $event->id)
        ->set('voidReason', 'Registro duplicado validado por RH')
        ->call('voidEvent')
        ->assertHasNoErrors()
        ->assertSee('Evento de jornada anulado.')
        ->assertSee('Anulado');

    $this->assertDatabaseHas('time_events', [
        'id' => $event->id,
        'company_id' => $company->id,
        'status' => 'voided',
        'voided_by_user_id' => $user->id,
        'void_reason' => 'Registro duplicado validado por RH',
    ]);
});

it('manual livewire approves pending manual event and refreshes work day', function (): void {
    [$company, $worker, $relationship] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);
    $event = TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'employment_relationship_id' => $relationship->id,
        'center_id' => $relationship->center_id,
        'event_type' => 'clock_in',
        'occurred_at_utc' => '2026-08-16 14:05:00',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '08:05:00',
        'timezone' => 'America/Mexico_City',
        'received_at' => '2026-08-16 14:06:00',
        'source' => 'admin_manual',
        'status' => 'pending_review',
        'metadata' => ['reason' => 'Entrada omitida validada por RH'],
    ]);

    expect(WorkDay::query()->where('company_id', $company->id)->count())->toBe(0);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->call('approveManualEvent', $event->id)
        ->assertHasNoErrors()
        ->assertSee('Captura manual aprobada y jornadas actualizadas.');

    $approved = $event->refresh();
    expect($approved->status)->toBe('valid')
        ->and($approved->metadata['review']['decision'])->toBe('approved')
        ->and($approved->metadata['review']['actor_user_id'])->toBe($user->id)
        ->and($approved->metadata['review']['previous_status'])->toBe('pending_review')
        ->and($approved->metadata['review']['resulting_status'])->toBe('valid');

    $workDay = WorkDay::query()
        ->where('company_id', $company->id)
        ->where('employment_relationship_id', $relationship->id)
        ->whereDate('work_date', '2026-08-16')
        ->firstOrFail();

    expect($workDay->schedule_status)->toBe(WorkDay::SCHEDULE_STATUS_UNSCHEDULED)
        ->and($workDay->valid_time_event_count)->toBe(1)
        ->and($workDay->valid_time_event_ids)->toBe([$event->id]);
});

it('manual livewire rejects pending manual event with required reason', function (): void {
    [$company, $worker, $relationship] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);
    $event = TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'employment_relationship_id' => $relationship->id,
        'center_id' => $relationship->center_id,
        'event_type' => 'clock_out',
        'occurred_at_utc' => '2026-08-16 23:05:00',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '17:05:00',
        'timezone' => 'America/Mexico_City',
        'received_at' => '2026-08-16 23:06:00',
        'source' => 'admin_manual',
        'status' => 'pending_review',
        'metadata' => ['reason' => 'Salida omitida por prueba'],
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->call('startReject', $event->id)
        ->set('rejectReason', 'No coincide con evidencia revisada')
        ->call('rejectManualEvent')
        ->assertHasNoErrors()
        ->assertSee('Captura manual rechazada.');

    $rejected = $event->refresh();
    expect($rejected->status)->toBe('ignored')
        ->and($rejected->metadata['review']['decision'])->toBe('rejected')
        ->and($rejected->metadata['review']['reason'])->toBe('No coincide con evidencia revisada')
        ->and(WorkDay::query()->where('company_id', $company->id)->count())->toBe(0);
});

it('manual review cannot be applied twice or by supervisor', function (): void {
    [$company, $worker, $relationship] = sprint2fManualFixture();
    $rh = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);
    $supervisor = sprint2fManualUserWithCompany($company, 'supervisor');
    $event = TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'employment_relationship_id' => $relationship->id,
        'center_id' => $relationship->center_id,
        'event_type' => 'clock_in',
        'occurred_at_utc' => '2026-08-16 14:05:00',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '08:05:00',
        'timezone' => 'America/Mexico_City',
        'received_at' => '2026-08-16 14:06:00',
        'source' => 'admin_manual',
        'status' => 'pending_review',
        'metadata' => ['reason' => 'Entrada omitida para revision'],
    ]);

    expect(fn () => app(\App\Domains\TimeRecords\Actions\ApproveManualTimeEventAction::class)->handle($event, $supervisor))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);

    app(\App\Domains\TimeRecords\Actions\ApproveManualTimeEventAction::class)->handle($event, $rh);

    expect(fn () => app(\App\Domains\TimeRecords\Actions\RejectManualTimeEventAction::class)->handle($event->refresh(), $rh, 'Segundo intento'))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});

it('manual capture preserves existing events and does not void replace or delete', function (): void {
    [$company, $worker] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);
    $action = app(RegisterManualTimeEventAction::class);

    $first = $action->handle($company, $user, $worker, [
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '08:00',
        'reason' => 'Entrada omitida',
    ]);
    $second = $action->handle($company, $user, $worker, [
        'event_type' => 'clock_out',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '17:00',
        'reason' => 'Salida omitida',
    ]);

    expect($first->exists)->toBeTrue()
        ->and($second->exists)->toBeTrue()
        ->and(TimeEvent::query()->where('worker_id', $worker->id)->count())->toBe(2)
        ->and(TimeEvent::query()->whereIn('status', ['voided', 'replaced'])->count())->toBe(0);
});

it('manual capture blocks inactive company by policy', function (): void {
    [$company, $worker] = sprint2fManualFixture(companyAttributes: ['status' => 'inactive']);
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);

    expect(fn () => app(RegisterManualTimeEventAction::class)->handle($company, $user, $worker, [
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '08:00',
        'reason' => 'Prueba manual',
    ]))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});

it('manual capture blocks inactive worker by domain validation', function (): void {
    [$company, $worker] = sprint2fManualFixture(workerAttributes: ['status' => 'inactive']);
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);

    expect(fn () => app(RegisterManualTimeEventAction::class)->handle($company, $user, $worker, [
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '08:00',
        'reason' => 'Prueba manual',
    ]))->toThrow(InvalidArgumentException::class);
});

it('manual capture creates only time events and no future modules', function (): void {
    [$company, $worker] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, RoleKey::RH_ADMIN);

    app(RegisterManualTimeEventAction::class)->handle($company, $user, $worker, [
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '08:00',
        'reason' => 'Captura manual justificada',
    ]);

    expect(Schema::hasTable('time_events'))->toBeTrue()
        ->and(Schema::hasTable('work_days'))->toBeTrue()
        ->and(Schema::hasTable('work_day_calculations'))->toBeTrue()
        ->and(Schema::hasTable('alerts'))->toBeTrue()
        ->and(Schema::hasTable('incidents'))->toBeFalse()
        ->and(Schema::hasTable('reports'))->toBeFalse()
        ->and(route('kiosk.index'))->toContain('/kiosk')
        ->and(route('time-events.manual'))->toContain('/time-events/manual');
});

/**
 * @return array{0: Company, 1: Worker, 2: EmploymentRelationship, 3: Center}
 */
function sprint2fManualFixture(array $companyAttributes = [], array $workerAttributes = [], array $centerAttributes = []): array
{
    $company = Company::factory()->create(array_replace([
        'status' => 'active',
        'timezone' => 'America/Mexico_City',
    ], $companyAttributes));
    $worker = Worker::factory()->create(array_replace([
        'company_id' => $company->id,
        'status' => 'active',
    ], $workerAttributes));
    $center = Center::factory()->create(array_replace([
        'company_id' => $company->id,
        'status' => 'active',
        'timezone' => 'America/Mexico_City',
    ], $centerAttributes));
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'status' => 'active',
    ]);

    return [$company, $worker, $relationship, $center];
}

function sprint2fManualUserWithCompany(Company $company, string $roleKey = RoleKey::ADMIN_EMPRESA): User
{
    $role = Role::query()->firstOrCreate(
        ['key' => $roleKey],
        ['name' => $roleKey, 'description' => null, 'is_system' => true]
    );
    $user = User::factory()->create();

    $user->companies()->attach($company, [
        'role_id' => $role->id,
        'status' => 'active',
        'is_default' => true,
    ]);

    return $user;
}
