<?php

namespace Tests\Feature\Companies;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CompanyExistingAdministratorReuseFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_reuse_an_unassigned_existing_administrator_without_a_temporary_password(): void
    {
        $adminRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $currentCompany = Company::factory()->create(['status' => 'active']);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);
        $existingAdministrator = User::factory()->create([
            'email' => 'sin.empresa@example.test',
            'status' => 'active',
            'global_role' => null,
        ]);

        $this->actingAs($superAdmin)->withSession(['current_company_id' => $currentCompany->id]);

        Volt::test('companies.index')
            ->set('createForm.name', 'Empresa reutiliza cuenta')
            ->set('createForm.legal_name', 'Empresa Reutiliza Cuenta SA de CV')
            ->set('createForm.tax_id', 'REU261007AA1')
            ->set('createForm.timezone', 'America/Mexico_City')
            ->set('createForm.account_type', 'single_company')
            ->set('createForm.admin_name', 'Cuenta existente')
            ->set('createForm.admin_email', $existingAdministrator->email)
            ->set('createForm.admin_password', '')
            ->call('create')
            ->assertHasNoErrors()
            ->assertSee('usuario existente asignado como administrador');

        $company = Company::query()->where('tax_id', 'REU261007AA1')->firstOrFail();

        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $existingAdministrator->id,
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);
    }
}
