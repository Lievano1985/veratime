<?php

use App\Domains\Workers\Actions\CreateWorkerAction;
use App\Domains\Workers\Actions\DeleteWorkerIfUnusedAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

it('shows generated credentials after creating a worker through the form', function () {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
    $actor = User::factory()->create();
    $actor->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
    $this->actingAs($actor)->withSession(['current_company_id' => $company->id]);
    $component = Volt::test('workers.index')->call('openCreatePanel')
        ->set('form.full_name', 'Persona con acceso')->set('form.employee_code', 'WEB-1')
        ->set('form.email', 'web@example.test')
        ->set('form.center_id', (string) $center->id)->call('save')->assertHasNoErrors()
        ->assertSee('Cuenta de trabajador creada');
    $credentials = $component->get('createdAccount');
    $user = User::query()->where('email', $credentials['email'])->sole();
    expect(Hash::check($credentials['password'], $user->password))->toBeTrue();
    $component->call('$set', 'createdAccount', null)->assertDontSee('Cuenta de trabajador creada');
});

it('creates a linked worker account with the worker role and hashed password', function () {
    $company = Company::factory()->create();
    $worker = app(CreateWorkerAction::class)->handle($company, [
        'employee_code' => 'W-1', 'full_name' => 'Persona nueva',
        'email' => ' PERSONAL@example.test ', 'account_password' => 'Initial-secret-123',
    ]);
    $link = $worker->userLinks()->with('user')->sole();
    expect($link->company_id)->toBe($company->id)
        ->and($link->user->email)->toBe('personal@example.test')
        ->and($link->user->roleKeyForCompany($company))->toBe(RoleKey::TRABAJADOR)
        ->and($link->user->global_role)->toBeNull()
        ->and(Hash::check('Initial-secret-123', $link->user->password))->toBeTrue();
});

it('rejects workers without email without creating accounts or workers', function () {
    $company = Company::factory()->create();
    expect(fn () => app(CreateWorkerAction::class)->handle($company, ['employee_code' => 'SAME', 'full_name' => 'Persona']))
        ->toThrow(ValidationException::class);
    $this->assertDatabaseCount('workers', 0);
    $this->assertDatabaseCount('users', 0);
});

it('rejects an existing account email without attaching it or leaving a worker behind', function () {
    $existing = User::factory()->create(['email' => 'existing@example.test']);
    $company = Company::factory()->create();
    try {
        app(CreateWorkerAction::class)->handle($company, ['employee_code' => 'W-1', 'full_name' => 'Persona', 'email' => $existing->email]);
        $this->fail('Expected duplicate account rejection');
    } catch (ValidationException) {
        expect(Worker::query()->count())->toBe(0)
            ->and($existing->companies()->count())->toBe(0);
    }
});

it('can delete an unused auto linked worker while disabling its company membership', function () {
    $company = Company::factory()->create();
    $worker = app(CreateWorkerAction::class)->handle($company, ['employee_code' => 'W-1', 'full_name' => 'Persona', 'email' => 'delete@example.test']);
    $user = $worker->userLinks()->with('user')->sole()->user;
    app(DeleteWorkerIfUnusedAction::class)->handle($worker);
    expect($worker->fresh())->toBeNull()
        ->and($user->companies()->first()->pivot->status)->toBe('inactive');
    $this->assertDatabaseCount('user_worker_links', 0);
});
