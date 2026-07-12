<?php

use App\Domains\TimeRecords\Actions\RegisterKioskTimeEventAction;
use App\Domains\TimeRecords\Actions\ResolveKioskCredentialAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\TimeEvent;
use App\Models\Worker;
use App\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-17 15:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('loads kiosk screen without authenticated user', function (): void {
    $this->get(route('kiosk.index'))
        ->assertOk()
        ->assertSee('Kiosco')
        ->assertSee('Codigo de acceso');
});

it('unknown code fails with neutral message', function (): void {
    Volt::test('kiosk.index')
        ->set('accessCode', 'NO-EXISTE')
        ->set('pin', '1234')
        ->call('identify')
        ->assertHasErrors(['accessCode'])
        ->assertSee('No se pudo validar la credencial.');
});

it('wrong pin fails increments attempts and never exposes pin or hash', function (): void {
    [, , , , $credential] = sprint2fKioskFixture(pin: '1234');

    Volt::test('kiosk.index')
        ->set('accessCode', $credential->access_code)
        ->set('pin', '9999')
        ->call('identify')
        ->assertHasErrors(['accessCode'])
        ->assertDontSee('9999')
        ->assertDontSee($credential->pin_hash);

    expect($credential->refresh()->failed_attempts)->toBe(1);
});

it('correct pin identifies worker and clears pin from component state', function (): void {
    [, $worker, , , $credential] = sprint2fKioskFixture(pin: '1234');

    Volt::test('kiosk.index')
        ->set('accessCode', $credential->access_code)
        ->set('pin', '1234')
        ->call('identify')
        ->assertHasNoErrors()
        ->assertSet('workerName', $worker->full_name)
        ->assertSet('pin', '')
        ->assertSee('Registrar entrada')
        ->assertDontSee($credential->pin_hash);

    expect($credential->refresh()->failed_attempts)->toBe(0)
        ->and($credential->last_used_at)->not->toBeNull();
});

it('can identify by employee code when access code is not used', function (): void {
    [, $worker, , , $credential] = sprint2fKioskFixture(pin: '1234');

    Volt::test('kiosk.index')
        ->set('accessCode', $worker->employee_code)
        ->set('pin', '1234')
        ->call('identify')
        ->assertHasNoErrors()
        ->assertSet('workerName', $worker->full_name)
        ->assertDontSee($credential->pin_hash);
});

it('blocked and reset required credentials cannot register', function (string $status): void {
    [, , , , $credential] = sprint2fKioskFixture(pin: '1234', credentialAttributes: ['status' => $status]);

    Volt::test('kiosk.index')
        ->set('accessCode', $credential->access_code)
        ->set('pin', '1234')
        ->call('identify')
        ->assertHasErrors(['accessCode']);
})->with(['blocked', 'reset_required']);

it('inactive worker or inactive company cannot register from kiosk', function (array $companyAttributes, array $workerAttributes): void {
    [, , , , $credential] = sprint2fKioskFixture(
        pin: '1234',
        companyAttributes: $companyAttributes,
        workerAttributes: $workerAttributes,
    );

    expect(fn () => app(ResolveKioskCredentialAction::class)->handle($credential->access_code, '1234'))
        ->toThrow(InvalidArgumentException::class);
})->with([
    [['status' => 'inactive'], []],
    [[], ['status' => 'inactive']],
]);

it('kiosk clock in creates time event with source kiosk and current system time', function (): void {
    [$company, $worker, $relationship, $center, $credential] = sprint2fKioskFixture(pin: '1234');

    $event = app(RegisterKioskTimeEventAction::class)->handle($credential, 'clock_in');

    expect($event->event_type)->toBe('clock_in')
        ->and($event->source)->toBe('kiosk')
        ->and($event->status)->toBe('valid')
        ->and($event->company_id)->toBe($company->id)
        ->and($event->worker_id)->toBe($worker->id)
        ->and($event->employment_relationship_id)->toBe($relationship->id)
        ->and($event->center_id)->toBe($center->id)
        ->and($event->source_user_id)->toBeNull()
        ->and($event->timezone)->toBe('America/Mexico_City')
        ->and($event->occurred_local_time)->toMatch('/^\d{2}:\d{2}:\d{2}$/')
        ->and($event->occurred_at_utc->utc()->format('Y-m-d H:i:s'))->toBe('2026-08-17 15:00:00')
        ->and($event->received_at)->not->toBeNull()
        ->and($event->metadata['channel'])->toBe('kiosk')
        ->and($event->metadata)->not->toHaveKey('pin');
});

it('kiosk action does not accept explicit occurrence time in sprint 2f', function (): void {
    $method = new ReflectionMethod(RegisterKioskTimeEventAction::class, 'handle');

    expect($method->getNumberOfParameters())->toBe(2);
});

it('handles kiosk clock out and break sequence and blocks invalid actions', function (): void {
    [, , , , $credential] = sprint2fKioskFixture(pin: '1234');
    $action = app(RegisterKioskTimeEventAction::class);

    expect(fn () => $action->handle($credential, 'clock_out'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $action->handle($credential, 'break_start'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $action->handle($credential, 'break_end'))->toThrow(InvalidArgumentException::class);

    $action->handle($credential, 'clock_in');
    expect(fn () => $action->handle($credential, 'clock_in'))->toThrow(InvalidArgumentException::class);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-17 18:00:00', 'UTC'));
    $action->handle($credential, 'break_start');
    expect(fn () => $action->handle($credential, 'break_start'))->toThrow(InvalidArgumentException::class);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-17 18:30:00', 'UTC'));
    $action->handle($credential, 'break_end');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-17 23:00:00', 'UTC'));
    $action->handle($credential, 'clock_out');

    expect(TimeEvent::query()->where('source', 'kiosk')->count())->toBe(4);
});

it('kiosk livewire records event then returns to safe start state', function (): void {
    [, $worker, , , $credential] = sprint2fKioskFixture(pin: '1234');

    Volt::test('kiosk.index')
        ->set('accessCode', $credential->access_code)
        ->set('pin', '1234')
        ->call('identify')
        ->assertSee($worker->full_name)
        ->call('record', 'clock_in')
        ->assertHasNoErrors()
        ->assertSee('Entrada registrada.')
        ->assertSet('accessCode', '')
        ->assertSet('pin', '')
        ->assertSet('credentialToken', null);
});

it('kiosk temporary token allows current registration and expires safely', function (): void {
    [, $worker, , , $credential] = sprint2fKioskFixture(pin: '1234');

    Volt::test('kiosk.index')
        ->set('accessCode', $credential->access_code)
        ->set('pin', '1234')
        ->call('identify')
        ->assertSee($worker->full_name)
        ->call('record', 'clock_in')
        ->assertHasNoErrors()
        ->assertSet('credentialToken', null);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-17 15:10:00', 'UTC'));

    Volt::test('kiosk.index')
        ->set('credentialToken', encrypt(json_encode([
            'credential_id' => $credential->id,
            'worker_id' => $credential->worker_id,
            'issued_at' => CarbonImmutable::parse('2026-08-17 15:00:00', 'UTC')->timestamp,
        ], JSON_THROW_ON_ERROR)))
        ->set('workerName', $worker->full_name)
        ->set('localDate', '2026-08-17')
        ->set('timezone', 'America/Mexico_City')
        ->set('allowedActions', ['clock_in'])
        ->call('record', 'clock_in')
        ->assertHasErrors(['accessCode'])
        ->assertSet('accessCode', '')
        ->assertSet('pin', '')
        ->assertSet('credentialToken', null)
        ->assertSet('workerName', null)
        ->assertSet('allowedActions', []);
});
it('kiosk blocks manipulated credential token for another worker', function (): void {
    [, , , , $credential] = sprint2fKioskFixture(pin: '1234');
    [, , , , $otherCredential] = sprint2fKioskFixture(pin: '1234');

    Volt::test('kiosk.index')
        ->set('accessCode', $credential->access_code)
        ->set('pin', '1234')
        ->call('identify')
        ->set('credentialToken', encrypt(json_encode([
            'credential_id' => $otherCredential->id,
            'worker_id' => $credential->worker_id,
            'issued_at' => now()->timestamp,
        ], JSON_THROW_ON_ERROR)))
        ->call('record', 'clock_in')
        ->assertHasErrors(['accessCode']);
});

it('sprint 2f kiosk creates only time events and no future modules', function (): void {
    [, , , , $credential] = sprint2fKioskFixture(pin: '1234');

    app(RegisterKioskTimeEventAction::class)->handle($credential, 'clock_in');

    expect(Schema::hasTable('time_events'))->toBeTrue()
        ->and(Schema::hasTable('work_days'))->toBeFalse()
        ->and(Schema::hasTable('work_day_calculations'))->toBeFalse()
        ->and(Schema::hasTable('alerts'))->toBeFalse()
        ->and(Schema::hasTable('incidents'))->toBeFalse()
        ->and(Schema::hasTable('reports'))->toBeFalse()
        ->and(Schema::hasTable('kiosk_sessions'))->toBeFalse();
});

/**
 * @return array{0: Company, 1: Worker, 2: EmploymentRelationship, 3: Center, 4: WorkerCredential}
 */
function sprint2fKioskFixture(
    string $pin = '1234',
    array $companyAttributes = [],
    array $workerAttributes = [],
    array $credentialAttributes = [],
): array {
    $company = Company::factory()->create(array_replace([
        'status' => 'active',
        'timezone' => 'America/Mexico_City',
    ], $companyAttributes));
    $worker = Worker::factory()->create(array_replace([
        'company_id' => $company->id,
        'status' => 'active',
    ], $workerAttributes));
    $center = Center::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
        'timezone' => 'America/Mexico_City',
    ]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'status' => 'active',
    ]);
    $credential = WorkerCredential::factory()->create(array_replace([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'pin_hash' => Hash::make($pin),
        'status' => 'active',
        'failed_attempts' => 0,
    ], $credentialAttributes));

    return [$company, $worker, $relationship, $center, $credential];
}