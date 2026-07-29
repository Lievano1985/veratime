<?php

use App\Domains\TimeRecords\Actions\RegisterManualTimeEventAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
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
    $user = sprint2fManualUserWithCompany($company, 'rh');

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->assertSee('Captura manual')
        ->assertSee($worker->full_name);
});

it('manual selector only shows active company workers', function (): void {
    [$company, $worker] = sprint2fManualFixture();
    [, $otherWorker] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, 'rh');

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->assertSee($worker->full_name)
        ->assertDontSee($otherWorker->full_name);
});

it('manual capture validates required fields and invalid type', function (): void {
    [$company] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, 'rh');

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
    $user = sprint2fManualUserWithCompany($company, 'rh');

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

it('manual action creates pending review event with source user reason and local utc conversion', function (): void {
    [$company, $worker, $relationship, $center] = sprint2fManualFixture(centerAttributes: ['timezone' => 'America/Tijuana']);
    $user = sprint2fManualUserWithCompany($company, 'rh');

    $event = app(RegisterManualTimeEventAction::class)->handle($company, $user, $worker, [
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '08:05',
        'reason' => 'Olvido registrar entrada',
    ]);

    expect($event->event_type)->toBe('clock_in')
        ->and($event->source)->toBe('admin_manual')
        ->and($event->status)->toBe('pending_review')
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
        ->and($event->metadata)->toBe([
            'channel' => 'manual',
            'reason' => 'Olvido registrar entrada',
            'captured_by' => $user->id,
            'context' => 'manual_justified_entry',
        ]);
});

it('manual livewire creates event and lists only active company manual captures', function (): void {
    [$company, $worker] = sprint2fManualFixture();
    [$otherCompany, $otherWorker] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, 'rh');
    $otherUser = sprint2fManualUserWithCompany($otherCompany, 'rh');

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
        ->assertSee('Captura manual guardada para revision.')
        ->assertSee($worker->full_name)
        ->assertDontSee($otherWorker->full_name);

    $this->assertDatabaseHas('time_events', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'source' => 'admin_manual',
        'event_type' => 'clock_out',
        'status' => 'pending_review',
    ]);
});

it('manual livewire voids a visible company event with required reason', function (): void {
    [$company, $worker, $relationship, $center] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, 'rh');
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

it('manual capture preserves existing events and does not void replace or delete', function (): void {
    [$company, $worker] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, 'rh');
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
    $user = sprint2fManualUserWithCompany($company, 'rh');

    expect(fn () => app(RegisterManualTimeEventAction::class)->handle($company, $user, $worker, [
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '08:00',
        'reason' => 'Prueba manual',
    ]))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});

it('manual capture blocks inactive worker by domain validation', function (): void {
    [$company, $worker] = sprint2fManualFixture(workerAttributes: ['status' => 'inactive']);
    $user = sprint2fManualUserWithCompany($company, 'rh');

    expect(fn () => app(RegisterManualTimeEventAction::class)->handle($company, $user, $worker, [
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '08:00',
        'reason' => 'Prueba manual',
    ]))->toThrow(InvalidArgumentException::class);
});

it('manual capture creates only time events and no future modules', function (): void {
    [$company, $worker] = sprint2fManualFixture();
    $user = sprint2fManualUserWithCompany($company, 'rh');

    app(RegisterManualTimeEventAction::class)->handle($company, $user, $worker, [
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-08-16',
        'occurred_local_time' => '08:00',
        'reason' => 'Captura manual justificada',
    ]);

    expect(Schema::hasTable('time_events'))->toBeTrue()
        ->and(Schema::hasTable('work_days'))->toBeFalse()
        ->and(Schema::hasTable('work_day_calculations'))->toBeFalse()
        ->and(Schema::hasTable('alerts'))->toBeFalse()
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

function sprint2fManualUserWithCompany(Company $company, string $roleKey = 'owner'): User
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
