<?php

namespace Tests\Feature\Sprint1A;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_company_and_is_attached_as_owner(): void
    {
        $ownerRole = Role::factory()->create(['key' => 'owner']);
        $user = User::factory()->create();
        $currentCompany = Company::factory()->create();

        $user->companies()->attach($currentCompany, [
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $currentCompany->id]);

        Volt::test('companies.index')
            ->set('createForm.name', 'Nueva Empresa')
            ->set('createForm.legal_name', 'Nueva Empresa SA de CV')
            ->set('createForm.tax_id', 'NUE260708AA1')
            ->set('createForm.timezone', 'America/Mexico_City')
            ->call('create');

        $company = Company::query()->where('tax_id', 'NUE260708AA1')->firstOrFail();

        $this->assertDatabaseHas('company_user', [
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'payroll_period_type' => 'biweekly',
        ]);
    }

    public function test_admin_can_update_company_basic_data_and_status(): void
    {
        $adminRole = Role::factory()->create(['key' => 'admin']);
        $user = User::factory()->create();
        $company = Company::factory()->create(['name' => 'Nombre anterior']);

        $user->companies()->attach($company, [
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('companies.index')
            ->set('editForm.name', 'Nombre actualizado')
            ->set('editForm.legal_name', 'Razon actualizada SA de CV')
            ->set('editForm.tax_id', 'ACT260708AA1')
            ->set('editForm.timezone', 'America/Mexico_City')
            ->set('editForm.status', 'active')
            ->call('update');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Nombre actualizado',
            'tax_id' => 'ACT260708AA1',
            'status' => 'active',
        ]);
    }

    public function test_marking_current_company_inactive_clears_current_company_session(): void
    {
        $adminRole = Role::factory()->create(['key' => 'admin']);
        $user = User::factory()->create();
        $company = Company::factory()->create(['name' => 'Empresa activa']);

        $user->companies()->attach($company, [
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('companies.index')
            ->set('editForm.name', 'Empresa activa')
            ->set('editForm.legal_name', $company->legal_name)
            ->set('editForm.tax_id', $company->tax_id)
            ->set('editForm.timezone', $company->timezone)
            ->set('editForm.status', 'inactive')
            ->call('update');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'status' => 'inactive',
        ]);
        $this->assertNull(session('current_company_id'));
    }

    public function test_non_admin_cannot_create_or_update_company(): void
    {
        $role = Role::factory()->create(['key' => 'supervisor']);
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $this->assertFalse($user->can('create', Company::class));
        $this->assertFalse($user->can('update', $company));

        $this->get(route('companies.index'))->assertOk();
    }
}
