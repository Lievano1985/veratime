<?php

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;

it('does not show company catalog links that a worker cannot access', function (): void {
    $company = Company::factory()->create(['status' => 'active']);
    $role = Role::factory()->create(['key' => RoleKey::TRABAJADOR]);
    $user = User::factory()->create(['status' => 'active']);

    $user->companies()->attach($company, [
        'role_id' => $role->id,
        'status' => 'active',
        'is_default' => true,
    ]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $company->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Centros')
        ->assertDontSee('Descansos obligatorios')
        ->assertDontSee('Horarios');
});
