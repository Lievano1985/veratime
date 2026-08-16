<?php

use App\Domains\TimeRecords\Actions\RegisterWebTimeEventAction;
use App\Domains\TimeRecords\Actions\ResolveCurrentTimeRecordStateAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-14 14:30:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('guest cannot access time clock route', function (): void {
    $this->get(route('time-clock.index'))
        ->assertRedirect(route('login'));
});

it('authenticated user without active company cannot access time clock route', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('time-clock.index'))
        ->assertForbidden();
});

it('inactive company blocks time clock operations', function (): void {
    [$company] = webTimeFixture(companyAttributes: ['status' => 'inactive']);
    $user = webTimeUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-clock.index')
        ->assertForbidden();
});

it('unauthorized role cannot use time clock', function (): void {
    [$company] = webTimeFixture();
    $user = webTimeUserWithCompany($company, 'supervisor');

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-clock.index')
        ->assertForbidden();
});

it('sidebar shows time clock only to authorized roles', function (): void {
    [$company] = webTimeFixture();
    $authorized = webTimeUserWithCompany($company, RoleKey::RH_ADMIN);
    $unauthorized = webTimeUserWithCompany($company, 'supervisor');

    $this->actingAs($authorized)->withSession(['current_company_id' => $company->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Eventos');

    $this->actingAs($unauthorized)->withSession(['current_company_id' => $company->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Registro de jornada');
});

it('manual capture screen supports assisted registration panel', function (): void {
    [$company, $worker] = webTimeFixture();
    $user = webTimeUserWithCompany($company, RoleKey::RH_ADMIN);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-events.manual')
        ->set('assistedWorkerId', (string) $worker->id)
        ->call('openAssistedPanel')
        ->assertSet('showAssistedPanel', true)
        ->assertSee('Captura asistida')
        ->call('recordAssisted', 'clock_in')
        ->assertSee('Entrada registrada.');

    expect(TimeEvent::query()
        ->where('company_id', $company->id)
        ->where('worker_id', $worker->id)
        ->where('source', 'web')
        ->where('event_type', 'clock_in')
        ->count())->toBe(1);
});

it('web registration action does not accept explicit occurrence time in sprint 2e', function (): void {
    $method = new \ReflectionMethod(RegisterWebTimeEventAction::class, 'handle');

    expect($method->getNumberOfParameters())->toBe(4);
});
it('authorized user records clock in from web', function (): void {
    [$company, $worker, $relationship, $center] = webTimeFixture();
    $user = webTimeUserWithCompany($company, RoleKey::RH_ADMIN);

    $event = app(RegisterWebTimeEventAction::class)->handle($company, $user, $worker, 'clock_in');

    expect($event->event_type)->toBe('clock_in')
        ->and($event->source)->toBe('web')
        ->and($event->status)->toBe('valid')
        ->and($event->company_id)->toBe($company->id)
        ->and($event->worker_id)->toBe($worker->id)
        ->and($event->employment_relationship_id)->toBe($relationship->id)
        ->and($event->center_id)->toBe($center->id)
        ->and($event->source_user_id)->toBe($user->id)
        ->and($event->timezone)->toBe('America/Mexico_City')
        ->and($event->occurred_local_date->toDateString())->toBe('2026-08-14')
        ->and($event->occurred_local_time)->toMatch('/^\d{2}:\d{2}:\d{2}$/')
        ->and($event->occurred_at_utc->utc()->format('Y-m-d H:i:s'))->toBe('2026-08-14 14:30:00')
        ->and($event->received_at)->not->toBeNull()
        ->and($event->metadata)->toBe([
            'channel' => 'web',
            'user_id' => $user->id,
            'context' => 'web_time_registration',
        ]);
});

it('does not allow double open clock in', function (): void {
    [$company, $worker] = webTimeFixture();
    $user = webTimeUserWithCompany($company);
    $action = app(RegisterWebTimeEventAction::class);

    $action->handle($company, $user, $worker, 'clock_in');

    expect(fn () => $action->handle($company, $user, $worker, 'clock_in'))
        ->toThrow(InvalidArgumentException::class);
});

it('allows clock out after clock in and blocks clock out without clock in', function (): void {
    [$company, $worker] = webTimeFixture();
    $user = webTimeUserWithCompany($company);
    $action = app(RegisterWebTimeEventAction::class);

    expect(fn () => $action->handle($company, $user, $worker, 'clock_out'))
        ->toThrow(InvalidArgumentException::class);

    $action->handle($company, $user, $worker, 'clock_in');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-14 23:30:00', 'UTC'));
    $clockOut = $action->handle($company, $user, $worker, 'clock_out');

    expect($clockOut->event_type)->toBe('clock_out')
        ->and(TimeEvent::query()->where('worker_id', $worker->id)->count())->toBe(2);
});

it('handles break sequence and allows clock out after break end', function (): void {
    [$company, $worker] = webTimeFixture();
    $user = webTimeUserWithCompany($company);
    $action = app(RegisterWebTimeEventAction::class);

    expect(fn () => $action->handle($company, $user, $worker, 'break_start'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $action->handle($company, $user, $worker, 'break_end'))
        ->toThrow(InvalidArgumentException::class);

    $action->handle($company, $user, $worker, 'clock_in');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-14 18:00:00', 'UTC'));
    $breakStart = $action->handle($company, $user, $worker, 'break_start');

    expect($breakStart->event_type)->toBe('break_start');
    expect(fn () => $action->handle($company, $user, $worker, 'break_start'))
        ->toThrow(InvalidArgumentException::class);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-14 18:30:00', 'UTC'));
    $breakEnd = $action->handle($company, $user, $worker, 'break_end');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-14 23:30:00', 'UTC'));
    $clockOut = $action->handle($company, $user, $worker, 'clock_out');

    expect($breakEnd->event_type)->toBe('break_end')
        ->and($clockOut->event_type)->toBe('clock_out')
        ->and(TimeEvent::query()->where('worker_id', $worker->id)->count())->toBe(4);
});

it('does not register worker from another company', function (): void {
    [$company] = webTimeFixture();
    [, $otherWorker] = webTimeFixture();
    $user = webTimeUserWithCompany($company);

    expect(fn () => app(RegisterWebTimeEventAction::class)->handle($company, $user, $otherWorker, 'clock_in'))
        ->toThrow(InvalidArgumentException::class);
});

it('does not allow center from another company through active relationship', function (): void {
    [$company, $worker] = webTimeFixture();
    $foreignCenter = Center::factory()->create(['company_id' => Company::factory()->create()->id]);
    EmploymentRelationship::query()->where('worker_id', $worker->id)->update(['status' => 'ended']);
    EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $foreignCenter->id,
        'status' => 'active',
    ]);
    $user = webTimeUserWithCompany($company);

    expect(fn () => app(RegisterWebTimeEventAction::class)->handle($company, $user, $worker, 'clock_in'))
        ->toThrow(InvalidArgumentException::class);
});

it('uses active company from context and lists only active company events', function (): void {
    [$company, $worker] = webTimeFixture();
    [$otherCompany, $otherWorker] = webTimeFixture();
    $user = webTimeUserWithCompany($company);
    app(RegisterWebTimeEventAction::class)->handle($otherCompany, webTimeUserWithCompany($otherCompany), $otherWorker, 'clock_in');

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-clock.index')
        ->set('workerId', (string) $worker->id)
        ->call('record', 'clock_in')
        ->assertHasNoErrors()
        ->assertSee($worker->full_name)
        ->assertDontSee($otherWorker->full_name);

    $this->assertDatabaseHas('time_events', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'source' => 'web',
    ]);
});

it('livewire blocks selecting worker from another company', function (): void {
    [$company] = webTimeFixture();
    [, $otherWorker] = webTimeFixture();
    $user = webTimeUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-clock.index')
        ->set('workerId', (string) $otherWorker->id)
        ->call('record', 'clock_in')
        ->assertHasErrors(['workerId']);
});

it('uses center timezone and falls back to company timezone', function (): void {
    [$company, $worker] = webTimeFixture(companyAttributes: ['timezone' => 'America/Mexico_City'], centerAttributes: ['timezone' => 'America/Tijuana']);
    $user = webTimeUserWithCompany($company);

    $centerEvent = app(RegisterWebTimeEventAction::class)->handle($company, $user, $worker, 'clock_in');

    expect($centerEvent->timezone)->toBe('America/Tijuana')
        ->and($centerEvent->occurred_local_date->toDateString())->toBe('2026-08-14')
        ->and($centerEvent->occurred_local_time)->toMatch('/^\d{2}:\d{2}:\d{2}$/')
        ->and($centerEvent->occurred_at_utc->utc()->format('Y-m-d H:i:s'))->toBe('2026-08-14 14:30:00');

    [$companyWithoutCenter, $workerWithoutCenter] = webTimeFixture(companyAttributes: ['timezone' => 'America/Merida'], withRelationship: false);
    $fallbackUser = webTimeUserWithCompany($companyWithoutCenter);
    $fallbackEvent = app(RegisterWebTimeEventAction::class)->handle($companyWithoutCenter, $fallbackUser, $workerWithoutCenter, 'clock_in');

    expect($fallbackEvent->timezone)->toBe('America/Merida');
});

it('resolves current state and allowed actions', function (): void {
    [$company, $worker] = webTimeFixture();
    $user = webTimeUserWithCompany($company);
    $register = app(RegisterWebTimeEventAction::class);
    $resolve = app(ResolveCurrentTimeRecordStateAction::class);

    $initial = $resolve->handle($company, $worker);
    expect($initial['state'])->toBe('sin_entrada')
        ->and($initial['allowed_actions'])->toBe(['clock_in']);

    $register->handle($company, $user, $worker, 'clock_in');
    $working = $resolve->handle($company, $worker);
    expect($working['state'])->toBe('trabajando')
        ->and($working['allowed_actions'])->toBe(['break_start', 'clock_out']);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-14 18:00:00', 'UTC'));
    $register->handle($company, $user, $worker, 'break_start');
    $paused = $resolve->handle($company, $worker);
    expect($paused['state'])->toBe('en_pausa')
        ->and($paused['allowed_actions'])->toBe(['break_end']);
});

it('shows buttons according to current state and success message', function (): void {
    [$company, $worker] = webTimeFixture();
    $user = webTimeUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-clock.index')
        ->assertSee('Registrar entrada')
        ->assertDontSee('Registrar salida')
        ->call('record', 'clock_in')
        ->assertHasNoErrors()
        ->assertSee('Entrada registrada.')
        ->assertSee('Registrar salida')
        ->assertSee('Iniciar pausa')
        ->assertDontSee('Registrar entrada');
});

it('shows neutral error for invalid action from ui', function (): void {
    [$company, $worker] = webTimeFixture();
    $user = webTimeUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('time-clock.index')
        ->set('workerId', (string) $worker->id)
        ->call('record', 'clock_out')
        ->assertHasErrors(['workerId']);
});

it('sprint 2e creates only time events and no calculation or kiosk modules', function (): void {
    [$company, $worker] = webTimeFixture();
    $user = webTimeUserWithCompany($company);

    app(RegisterWebTimeEventAction::class)->handle($company, $user, $worker, 'clock_in');

    expect(Schema::hasTable('time_events'))->toBeTrue()
        ->and(Schema::hasTable('work_days'))->toBeTrue()
        ->and(Schema::hasTable('work_day_calculations'))->toBeTrue()
        ->and(Schema::hasTable('alerts'))->toBeTrue()
        ->and(Schema::hasTable('incidents'))->toBeFalse()
        ->and(Schema::hasTable('reports'))->toBeFalse()
        ->and(Schema::hasTable('kiosk_sessions'))->toBeFalse();
});

/**
 * @return array{0: Company, 1: Worker, 2: ?EmploymentRelationship, 3: ?Center}
 */
function webTimeFixture(array $companyAttributes = [], array $centerAttributes = [], bool $withRelationship = true): array
{
    $company = Company::factory()->create(array_replace([
        'status' => 'active',
        'timezone' => 'America/Mexico_City',
    ], $companyAttributes));
    $worker = Worker::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
    ]);

    if (! $withRelationship) {
        return [$company, $worker, null, null];
    }

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

function webTimeUserWithCompany(Company $company, string $roleKey = RoleKey::ADMIN_EMPRESA): User
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
