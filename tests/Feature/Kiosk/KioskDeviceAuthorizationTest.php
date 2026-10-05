<?php

use App\Domains\TimeRecords\Actions\ApproveKioskTerminalAccessRequestAction;
use App\Domains\TimeRecords\Actions\ClaimKioskTerminalAccessRequestAction;
use App\Domains\TimeRecords\Actions\DeleteKioskDeviceAction;
use App\Domains\TimeRecords\Actions\RejectKioskTerminalAccessRequestAction;
use App\Domains\TimeRecords\Actions\RequestKioskTerminalAccessAction;
use App\Domains\TimeRecords\Actions\ResolveKioskDeviceAction;
use App\Domains\TimeRecords\Actions\RevokeKioskDeviceAction;
use App\Domains\TimeRecords\Actions\SetKioskEnrollmentKeyAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\KioskDevice;
use App\Models\KioskTerminalAccessRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Cookie;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 15:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('creates a pending terminal request without storing its secret in plain text', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $settings = app(SetKioskEnrollmentKeyAction::class)->handle($company, $manager, 'terminal-request-key');

    $request = app(RequestKioskTerminalAccessAction::class)->handle(
        $settings->kiosk_enrollment_identifier,
        'terminal-request-key',
        'Recepcion',
        '203.0.113.10',
        'Vera terminal test',
    );

    expect($request['request']->status)->toBe('pending')
        ->and($settings->kiosk_enrollment_identifier)->toMatch('/^VT-[A-Z0-9]{6}$/')
        ->and($request['request']->expires_at?->toDateTimeString())->toBe('2026-10-01 15:15:00')
        ->and($request['request']->request_secret_hash)->toBe(hash('sha256', $request['request_secret']))
        ->and($request['request']->getAttributes())->not->toHaveKey('request_secret')
        ->and(KioskDevice::query()->where('company_id', $company->id)->count())->toBe(0);
});

it('shows the public manual authorization screen without an administrator session', function (): void {
    $this->get(route('kiosk.authorize'))
        ->assertOk()
        ->assertSee('Solicitar terminal')
        ->assertSee('Codigo de empresa')
        ->assertSee('ui-toast', false)
        ->assertDontSee('class="flex w-full max-w-sm flex-col gap-2"', false);
});

it('uses a popup notification when the terminal request is incomplete', function (): void {
    Livewire::test('kiosk.authorize')
        ->call('requestAuthorization')
        ->assertHasErrors('enrollmentKey')
        ->assertDispatched('toast-show', function (string $name, array $params): bool {
            return $name === 'toast-show'
                && $params['slots']['text'] === 'Escribe un nombre valido para esta terminal.'
                && $params['dataset']['variant'] === 'danger';
        });
});

it('reissues a pending request cookie at the application root for Livewire polling', function (): void {
    $requestSecret = 'test-kiosk-request-secret';

    $response = $this->withCookie('vera_kiosk_terminal_request', $requestSecret)
        ->get(route('kiosk.authorize'))
        ->assertOk();

    expect(responseCookieValue($response, 'vera_kiosk_terminal_request', '/'))->not->toBeNull();
});

it('shows a success popup after a terminal has been authorized', function (): void {
    [$company] = kioskDeviceManager();
    $deviceToken = 'test-kiosk-device-token';

    KioskDevice::factory()->active()->create([
        'company_id' => $company->id,
        'device_token_hash' => hash('sha256', $deviceToken),
    ]);

    $this->withSession([
        'kiosk_toast' => [
            'text' => 'Terminal autorizada para '.$company->name.'.',
            'heading' => 'Terminal lista para usarse',
            'variant' => 'success',
        ],
    ])
        ->withCookie('vera_kiosk_device', $deviceToken)
        ->get(route('kiosk.index'))
        ->assertOk()
        ->assertSee('toast-show', false)
        ->assertSee('Terminal autorizada para '.$company->name.'.');
});

it('reissues a legacy terminal cookie at the application root for kiosco polling', function (): void {
    [$company] = kioskDeviceManager();
    $deviceToken = 'test-kiosk-device-token';

    KioskDevice::factory()->active()->create([
        'company_id' => $company->id,
        'device_token_hash' => hash('sha256', $deviceToken),
    ]);

    $response = $this->withCookie('vera_kiosk_device', $deviceToken)
        ->get(route('kiosk.index'))
        ->assertOk();

    expect(responseCookieValue($response, 'vera_kiosk_device', '/'))->not->toBeNull();
});

it('only creates an active terminal when its approved request is claimed once', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $settings = app(SetKioskEnrollmentKeyAction::class)->handle($company, $manager, 'terminal-request-key');
    $request = app(RequestKioskTerminalAccessAction::class)->handle($settings->kiosk_enrollment_identifier, 'terminal-request-key', 'Recepcion');

    app(ApproveKioskTerminalAccessRequestAction::class)->handle($request['request'], $manager);

    expect(KioskDevice::query()->where('company_id', $company->id)->count())->toBe(0)
        ->and($request['request']->refresh()->status)->toBe('approved');

    $claimed = app(ClaimKioskTerminalAccessRequestAction::class)->handle($request['request_secret'], '203.0.113.10', 'Vera terminal test');
    $device = $claimed['device'];

    expect($device->status)->toBe('active')
        ->and($device->device_token_hash)->toBe(hash('sha256', $claimed['device_token']))
        ->and($device->last_seen_ip)->toBe('203.0.113.10');

    expect(app(ClaimKioskTerminalAccessRequestAction::class)->handle($request['request_secret']))->toBe(['status' => 'claimed']);

    $resolved = app(ResolveKioskDeviceAction::class)->handle($claimed['device_token'], '203.0.113.11', 'Other terminal test');

    expect($resolved?->id)->toBe($device->id)
        ->and($resolved?->last_seen_ip)->toBe('203.0.113.11')
        ->and(app(ResolveKioskDeviceAction::class)->handle('wrong-secret'))->toBeNull();
});

it('does not create a request when the enrollment key is invalid', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $settings = app(SetKioskEnrollmentKeyAction::class)->handle($company, $manager, 'terminal-request-key');

    expect(fn () => app(RequestKioskTerminalAccessAction::class)->handle($settings->kiosk_enrollment_identifier, 'incorrect-key', 'Recepcion'))
        ->toThrow(InvalidArgumentException::class);

    expect(KioskTerminalAccessRequest::query()->where('company_id', $company->id)->count())->toBe(0);
});

it('does not issue a terminal credential when the administrator rejects the request', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $settings = app(SetKioskEnrollmentKeyAction::class)->handle($company, $manager, 'terminal-request-key');
    $request = app(RequestKioskTerminalAccessAction::class)->handle($settings->kiosk_enrollment_identifier, 'terminal-request-key', 'Recepcion');

    app(RejectKioskTerminalAccessRequestAction::class)->handle($request['request'], $manager);

    expect(app(ClaimKioskTerminalAccessRequestAction::class)->handle($request['request_secret']))->toBe(['status' => 'rejected'])
        ->and(KioskDevice::query()->where('company_id', $company->id)->count())->toBe(0);
});

it('expires pending requests when the enrollment key is rotated', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $settings = app(SetKioskEnrollmentKeyAction::class)->handle($company, $manager, 'terminal-request-key');
    $request = app(RequestKioskTerminalAccessAction::class)->handle($settings->kiosk_enrollment_identifier, 'terminal-request-key', 'Recepcion');

    app(SetKioskEnrollmentKeyAction::class)->handle($company, $manager, 'replacement-request-key');

    expect($request['request']->refresh()->status)->toBe('expired');
});

it('does not approve a request that expired before administrative review', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $settings = app(SetKioskEnrollmentKeyAction::class)->handle($company, $manager, 'terminal-request-key');
    $request = app(RequestKioskTerminalAccessAction::class)->handle($settings->kiosk_enrollment_identifier, 'terminal-request-key', 'Recepcion');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 15:16:00', 'UTC'));

    expect(fn () => app(ApproveKioskTerminalAccessRequestAction::class)->handle($request['request'], $manager))
        ->toThrow(InvalidArgumentException::class);

    expect($request['request']->refresh()->status)->toBe('expired');
});

it('does not issue a credential when an approved request expires before its terminal claims it', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $settings = app(SetKioskEnrollmentKeyAction::class)->handle($company, $manager, 'terminal-request-key');
    $request = app(RequestKioskTerminalAccessAction::class)->handle($settings->kiosk_enrollment_identifier, 'terminal-request-key', 'Recepcion');

    app(ApproveKioskTerminalAccessRequestAction::class)->handle($request['request'], $manager);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 15:16:00', 'UTC'));

    expect(app(ClaimKioskTerminalAccessRequestAction::class)->handle($request['request_secret']))->toBe(['status' => 'expired'])
        ->and(KioskDevice::query()->where('company_id', $company->id)->count())->toBe(0);
});

it('revocation immediately makes a terminal secret unusable', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $authorized = authorizedKioskTerminal($company, $manager);

    app(RevokeKioskDeviceAction::class)->handle($authorized['device'], $manager);

    expect($authorized['device']->refresh()->status)->toBe('revoked')
        ->and($authorized['device']->device_token_hash)->toBeNull()
        ->and(app(ResolveKioskDeviceAction::class)->handle($authorized['device_token']))->toBeNull();
});

it('soft deletes a terminal and immediately invalidates its device secret', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $authorized = authorizedKioskTerminal($company, $manager);

    app(DeleteKioskDeviceAction::class)->handle($authorized['device'], $manager);

    $this->assertSoftDeleted('kiosk_devices', ['id' => $authorized['device']->id]);
    expect(app(ResolveKioskDeviceAction::class)->handle($authorized['device_token']))->toBeNull();
});

it('does not let a manager delete a terminal from another company', function (): void {
    [, $manager] = kioskDeviceManager();
    $otherCompany = Company::factory()->create(['status' => 'active']);
    $otherDevice = KioskDevice::factory()->active()->create(['company_id' => $otherCompany->id]);

    expect(fn () => app(DeleteKioskDeviceAction::class)->handle($otherDevice, $manager))
        ->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('kiosk_devices', ['id' => $otherDevice->id, 'deleted_at' => null]);
});

it('does not let a manager approve a request from another company', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $otherCompany = Company::factory()->create(['status' => 'active']);
    $otherManager = User::factory()->create();
    $otherManager->companies()->attach($otherCompany, [
        'role_id' => Role::query()->where('key', RoleKey::ADMIN_EMPRESA)->value('id'),
        'status' => 'active',
        'is_default' => true,
    ]);
    CompanySetting::query()->create(array_replace(Company::defaultSettings(), ['company_id' => $otherCompany->id]));
    $settings = app(SetKioskEnrollmentKeyAction::class)->handle($otherCompany, $otherManager, 'other-terminal-request-key');
    $request = app(RequestKioskTerminalAccessAction::class)->handle($settings->kiosk_enrollment_identifier, 'other-terminal-request-key', 'Recepcion');

    expect(fn () => app(ApproveKioskTerminalAccessRequestAction::class)->handle($request['request'], $manager))
        ->toThrow(AuthorizationException::class);

    expect($request['request']->refresh()->status)->toBe('pending');
});

it('cannot assign a terminal request to a center from another company', function (): void {
    [$company, $manager] = kioskDeviceManager();
    $settings = app(SetKioskEnrollmentKeyAction::class)->handle($company, $manager, 'terminal-request-key');
    $request = app(RequestKioskTerminalAccessAction::class)->handle($settings->kiosk_enrollment_identifier, 'terminal-request-key', 'Recepcion');
    $otherCenter = Center::factory()->create();

    expect(fn () => app(ApproveKioskTerminalAccessRequestAction::class)->handle($request['request'], $manager, $otherCenter->id))
        ->toThrow(InvalidArgumentException::class);

    expect($request['request']->refresh()->status)->toBe('pending');
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
        'require_pin_for_confirmation' => true,
    ], $override);
}

/** @return array{device: KioskDevice, device_token: string} */
function authorizedKioskTerminal(Company $company, User $manager): array
{
    $settings = app(SetKioskEnrollmentKeyAction::class)->handle($company, $manager, 'terminal-request-key');
    $request = app(RequestKioskTerminalAccessAction::class)->handle($settings->kiosk_enrollment_identifier, 'terminal-request-key', 'Recepcion');
    app(ApproveKioskTerminalAccessRequestAction::class)->handle($request['request'], $manager);

    return app(ClaimKioskTerminalAccessRequestAction::class)->handle($request['request_secret']);
}

function responseCookieValue(TestResponse $response, string $name, string $path): ?string
{
    return collect($response->headers->getCookies())
        ->first(fn (Cookie $cookie): bool => $cookie->getName() === $name && $cookie->getPath() === $path)
        ?->getValue();
}
