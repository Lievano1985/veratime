<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\TimeRecords\Actions\ResolveKioskCredentialAction;
use App\Domains\Workers\Actions\CreateOrUpdateWorkerCredentialAction;
use App\Domains\Workers\Actions\GeneratePersonalKioskPinAction;
use App\Livewire\Personal\KioskCredential;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function personalKioskContext(): array
{
    $company = Company::factory()->create(['status' => 'active']);
    $user = User::factory()->create(['status' => 'active']);
    $role = Role::firstOrCreate(['key' => RoleKey::TRABAJADOR], ['name' => 'Trabajador']);
    $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
    $worker = Worker::factory()->create(['company_id' => $company->id, 'status' => 'active']);
    UserWorkerLink::create(['company_id' => $company->id, 'worker_id' => $worker->id, 'user_id' => $user->id, 'status' => 'active']);

    return [$company, $user, $worker];
}

it('creates and renews only the linked worker credential using their employee number', function () {
    [$company, $user, $worker] = personalKioskContext();
    $action = app(GeneratePersonalKioskPinAction::class);
    $first = $action->handle($user, $company, 'password');
    $credential = $worker->credential()->firstOrFail();
    expect($first['code'])->toBe($worker->employee_code)
        ->and($first['pin'])->toMatch('/^\d{6}$/')
        ->and(Hash::check($first['pin'], $credential->pin_hash))->toBeTrue()
        ->and($credential->toArray())->not->toHaveKey('pin_hash');
    expect(app(ResolveKioskCredentialAction::class)->handle($company, $first['code'], $first['pin'])->worker_id)->toBe($worker->id);
    $credential->update(['pin_hash' => Hash::make('old-secret'), 'failed_attempts' => 3]);
    $second = $action->handle($user, $company, 'password');
    expect($credential->fresh()->id)->toBe($credential->id)
        ->and(Hash::check('old-secret', $credential->fresh()->pin_hash))->toBeFalse()
        ->and(Hash::check($second['pin'], $credential->fresh()->pin_hash))->toBeTrue()
        ->and($credential->fresh()->failed_attempts)->toBe(0);
});

it('limits repeated incorrect password attempts even if the next password is correct', function () {
    [$company, $user, $worker] = personalKioskContext();
    for ($i = 0; $i < 5; $i++) {
        try {
            app(GeneratePersonalKioskPinAction::class)->handle($user, $company, 'incorrect');
        } catch (ValidationException $exception) {
            expect($exception->errors())->toHaveKey('currentPassword');
        }
    }
    expect(fn () => app(GeneratePersonalKioskPinAction::class)->handle($user, $company, 'password'))
        ->toThrow(ValidationException::class, 'Has realizado demasiados intentos');
    expect($worker->credential()->exists())->toBeFalse();
});

it('does not overwrite another workers access code', function () {
    [$company, $user, $worker] = personalKioskContext();
    $other = Worker::factory()->create(['company_id' => $company->id]);
    $credential = app(CreateOrUpdateWorkerCredentialAction::class)
        ->handle($company, $other, ['access_code' => $worker->employee_code, 'temporal_pin' => '123456']);
    expect(fn () => app(GeneratePersonalKioskPinAction::class)->handle($user, $company, 'password'))
        ->toThrow(ValidationException::class);
    expect($worker->credential()->exists())->toBeFalse()
        ->and(Hash::check('123456', $credential->fresh()->pin_hash))->toBeTrue();
});

it('rejects incorrect account passwords without creating a credential', function () {
    [$company, $user, $worker] = personalKioskContext();
    expect(fn () => app(GeneratePersonalKioskPinAction::class)->handle($user, $company, 'incorrect'))
        ->toThrow(ValidationException::class);
    expect($worker->credential()->exists())->toBeFalse();
});

it('preserves an administrative block', function () {
    [$company, $user, $worker] = personalKioskContext();
    app(GeneratePersonalKioskPinAction::class)->handle($user, $company, 'password');
    $worker->credential()->update(['status' => 'blocked']);
    expect(fn () => app(GeneratePersonalKioskPinAction::class)->handle($user, $company, 'password'))
        ->toThrow(ValidationException::class);
    expect($worker->credential()->first()->status)->toBe('blocked');
});

it('rejects another company and revoked worker links', function () {
    [$company, $user] = personalKioskContext();
    expect(fn () => app(GeneratePersonalKioskPinAction::class)->handle($user, Company::factory()->create(), 'password'))
        ->toThrow(HttpException::class);
    UserWorkerLink::query()->where('user_id', $user->id)->update(['status' => 'revoked']);
    expect(fn () => app(GeneratePersonalKioskPinAction::class)->handle($user, $company, 'password'))
        ->toThrow(HttpException::class);
});

it('delivers the generated pin once and clears the account password', function () {
    [$company, $user, $worker] = personalKioskContext();
    $this->actingAs($user);
    app(CurrentCompany::class)->set($company);
    Livewire::test(KioskCredential::class)->call('openPanel')->assertSet('showPanel', true)
        ->set('currentPassword', 'password')->call('generatePin')->assertHasNoErrors()
        ->assertSet('currentPassword', '')
        ->assertDispatched('personal-kiosk-pin-generated', fn ($event, $data) => $data['code'] === $worker->employee_code && Hash::check($data['pin'], $worker->credential()->first()->pin_hash))
        ->call('closePanel')->assertSet('showPanel', false)->assertDispatched('personal-kiosk-pin-clear');
});
