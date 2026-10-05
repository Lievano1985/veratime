<?php

use App\Domains\Companies\Actions\UpdateCompanySettingsAction;
use App\Domains\TimeRecords\Actions\CreateKioskDevicePairingAction;
use App\Domains\TimeRecords\Actions\DeleteKioskDeviceAction;
use App\Domains\TimeRecords\Actions\PairKioskDeviceAction;
use App\Domains\TimeRecords\Actions\ResolveKioskDeviceAction;
use App\Domains\TimeRecords\Actions\RevokeKioskDeviceAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\KioskDevice;
use App\Models\Role;
use App\Models\User;
use App\Support\KioskKey;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 15:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('creates an hour-limited pairing whose secret is never stored in plain text', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $center = Center::factory()->create(['company_id' => $company->id]);

    $pairing = app(CreateKioskDevicePairingAction::class)->handle($company, $manager, 'Recepcion', $center->id);

    expect($pairing['pairing_code'])->toStartWith('VTK-')
        ->and($pairing['device']->status)->toBe('pending')
        ->and($pairing['device']->pairing_expires_at?->toDateTimeString())->toBe('2026-10-01 16:00:00')
        ->and($pairing['device']->pairing_code_hash)->toBe(hash('sha256', $pairing['pairing_code']))
        ->and($pairing['device']->getAttributes())->not->toHaveKey('device_token');
});

it('shows the public manual authorization screen without an administrator session', function (): void {
    $this->get(route('kiosk.authorize'))
        ->assertOk()
        ->assertSee('Autorizar terminal')
        ->assertSee('Codigo de autorizacion');
});

it('pairs a terminal once, resolves it only with its device secret, and records its last connection', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $pairing = app(CreateKioskDevicePairingAction::class)->handle($company, $manager, 'Recepcion');

    $authorized = app(PairKioskDeviceAction::class)->handle($pairing['pairing_code'], '203.0.113.10', 'Vera terminal test');
    $device = $authorized['device'];

    expect($device->status)->toBe('active')
        ->and($device->pairing_code_hash)->toBeNull()
        ->and($device->device_token_hash)->toBe(hash('sha256', $authorized['device_token']))
        ->and($device->last_seen_ip)->toBe('203.0.113.10');

    expect(fn () => app(PairKioskDeviceAction::class)->handle($pairing['pairing_code']))
        ->toThrow(InvalidArgumentException::class);

    $resolved = app(ResolveKioskDeviceAction::class)->handle($authorized['device_token'], '203.0.113.11', 'Other terminal test');

    expect($resolved?->id)->toBe($device->id)
        ->and($resolved?->last_seen_ip)->toBe('203.0.113.11')
        ->and(app(ResolveKioskDeviceAction::class)->handle('wrong-secret'))->toBeNull();
});

it('rejects an expired pairing code', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $pairing = app(CreateKioskDevicePairingAction::class)->handle($company, $manager, 'Recepcion');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 16:01:00', 'UTC'));

    expect(fn () => app(PairKioskDeviceAction::class)->handle($pairing['pairing_code']))
        ->toThrow(InvalidArgumentException::class);
});

it('revocation immediately makes a terminal secret unusable', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $pairing = app(CreateKioskDevicePairingAction::class)->handle($company, $manager, 'Recepcion');
    $authorized = app(PairKioskDeviceAction::class)->handle($pairing['pairing_code']);

    app(RevokeKioskDeviceAction::class)->handle($authorized['device'], $manager);

    expect($authorized['device']->refresh()->status)->toBe('revoked')
        ->and($authorized['device']->device_token_hash)->toBeNull()
        ->and(app(ResolveKioskDeviceAction::class)->handle($authorized['device_token']))->toBeNull();
});

it('soft deletes a terminal and immediately invalidates its device secret', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $pairing = app(CreateKioskDevicePairingAction::class)->handle($company, $manager, 'Recepcion');
    $authorized = app(PairKioskDeviceAction::class)->handle($pairing['pairing_code']);

    app(DeleteKioskDeviceAction::class)->handle($authorized['device'], $manager);

    $this->assertSoftDeleted('kiosk_devices', ['id' => $authorized['device']->id]);
    expect(app(ResolveKioskDeviceAction::class)->handle($authorized['device_token']))->toBeNull();
});

it('does not let a manager delete a terminal from another company', function (): void {
    [, $manager] = kioskDeviceManager();
    $otherCompany = Company::factory()->create(['status' => 'active']);
    $otherDevice = KioskDevice::factory()->active()->create(['company_id' => $otherCompany->id]);

    expect(fn () => app(DeleteKioskDeviceAction::class)->handle($otherDevice, $manager))
        ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);

    $this->assertDatabaseHas('kiosk_devices', ['id' => $otherDevice->id, 'deleted_at' => null]);
});

it('cannot create a pairing for another company center', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $otherCenter = Center::factory()->create();

    expect(fn () => app(CreateKioskDevicePairingAction::class)->handle($company, $manager, 'Recepcion', $otherCenter->id))
        ->toThrow(InvalidArgumentException::class);
});

it('does not allow strict authorized-terminal mode until a terminal is active', function (): void {
    [$company] = kioskDeviceManager();

    expect(fn () => app(UpdateCompanySettingsAction::class)->handle($company, kioskSettings(['require_authorized_kiosk_devices' => true])))
        ->toThrow(ValidationException::class);

    KioskDevice::query()->create([
        'company_id' => $company->id,
        'name' => 'Recepcion',
        'status' => 'active',
        'device_token_hash' => hash('sha256', 'active-terminal-secret'),
    ]);

    app(UpdateCompanySettingsAction::class)->handle($company, kioskSettings(['require_authorized_kiosk_devices' => true]));

    expect($company->setting()->firstOrFail()->require_authorized_kiosk_devices)->toBeTrue();
});

it('does not activate a strict company from the legacy shared key', function (): void {
    [$company] = kioskDeviceManager();
    KioskDevice::factory()->active()->create(['company_id' => $company->id]);
    $company->setting()->update([
        'kiosk_key_hash' => KioskKey::hash('KIOSK-KEY1!'),
        'require_authorized_kiosk_devices' => true,
    ]);

    Volt::test('kiosk.index')
        ->set('kioskKey', 'KIOSK-KEY1!')
        ->call('activateKiosk')
        ->assertHasErrors(['kioskKey'])
        ->assertSee('Esta empresa requiere una terminal autorizada.');
});

/** @return array{0: Company, 1: User} */
function kioskDeviceManager(): array
{
    $company = Company::factory()->create(['status' => 'active']);
    $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
    $manager = User::factory()->create();
    $manager->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
    CompanySetting::query()->create(array_replace(Company::defaultSettings(), ['company_id' => $company->id]));

    return [$company, $manager];
}

function kioskSettings(array $override = []): array
{
    return array_replace(Company::defaultSettings(), [
        'payroll_period_type' => 'biweekly',
        'default_timezone' => 'America/Mexico_City',
        'default_closure_day' => null,
        'work_days_auto_refresh_time' => null,
        'late_arrival_tolerance_minutes' => 0,
        'early_departure_tolerance_minutes' => 0,
        'allow_worker_corrections' => false,
        'require_pin_for_kiosk' => true,
        'kiosk_key' => '',
        'require_authorized_kiosk_devices' => false,
        'require_pin_for_confirmation' => true,
    ], $override);
}
