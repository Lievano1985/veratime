<?php

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\Worker;
use App\Support\RoleKey;
use Livewire\Volt\Volt;

function workerHomeContext(): array
{
    $company = Company::factory()->create(['status' => 'active']);
    $role = Role::factory()->create(['key' => RoleKey::TRABAJADOR]);
    $user = User::factory()->create(['status' => 'active']);
    $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
    $worker = Worker::factory()->create(['company_id' => $company->id, 'status' => 'active']);
    UserWorkerLink::create(['company_id' => $company->id, 'user_id' => $user->id, 'worker_id' => $worker->id, 'status' => 'active']);

    return [$company, $user];
}

it('takes workers to their personal page after login even with an obsolete intended destination', function () {
    [, $user] = workerHomeContext();
    $this->withSession(['url.intended' => '/time/dashboard']);
    Volt::test('auth.login')->set('email', $user->email)->set('password', 'password')->call('login')
        ->assertHasNoErrors()->assertRedirect('/time/my-dashboard');
});

it('redirects the worker dashboard to the working personal route while protecting administration', function () {
    [$company, $user] = workerHomeContext();
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    $this->get('/time/dashboard')->assertRedirect(route('personal.dashboard'));
    $this->get('/time/my-dashboard')->assertOk()->assertSee('Mi espacio personal')->assertSee('Mi resumen');
    $this->get('/time/my-day')->assertOk()->assertSee('Mi jornada')
        ->assertDontSee('Inicio')
        ->assertDontSee('Organización');
    $this->get('/time/workers')->assertForbidden();
});
