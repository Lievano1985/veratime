<?php

namespace Tests\Feature\Sprint1A;

use App\Domains\Companies\Jobs\GenerateCompanyDemoScenarioJob;
use App\Models\Company;
use App\Models\CompanyDemoScenario;
use App\Models\CustomerAccount;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CompanyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_empresa_can_create_company_and_is_attached_as_admin(): void
    {
        $ownerRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
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
        $this->assertDatabaseHas('customer_accounts', [
            'id' => $company->customer_account_id,
            'account_type' => 'single_company',
            'status' => 'active',
        ]);
    }

    public function test_admin_empresa_from_multi_company_account_creates_company_in_same_customer_account(): void
    {
        $adminRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $customerAccount = CustomerAccount::factory()->create([
            'name' => 'Cuenta multi',
            'account_type' => 'multi_company',
            'status' => 'active',
        ]);
        $user = User::factory()->create();
        $currentCompany = Company::factory()->create([
            'customer_account_id' => $customerAccount->id,
            'status' => 'active',
        ]);

        $user->companies()->attach($currentCompany, [
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $currentCompany->id]);

        Volt::test('companies.index')
            ->set('createForm.name', 'Empresa hija multi')
            ->set('createForm.legal_name', 'Empresa Hija Multi SA de CV')
            ->set('createForm.tax_id', 'MUL260821AA1')
            ->set('createForm.timezone', 'America/Mexico_City')
            ->call('create');

        $company = Company::query()->where('tax_id', 'MUL260821AA1')->firstOrFail();

        $this->assertSame($customerAccount->id, $company->customer_account_id);
        $this->assertSame(1, CustomerAccount::query()->count());
        $this->assertDatabaseHas('company_user', [
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);
    }

    public function test_company_creation_can_request_a_demo_scenario(): void
    {
        Queue::fake();
        $ownerRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create();
        $currentCompany = Company::factory()->create();
        $user->companies()->attach($currentCompany, [
            'role_id' => $ownerRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $currentCompany->id]);

        Volt::test('companies.index')
            ->set('createForm.name', 'Empresa con demo')
            ->set('createForm.legal_name', 'Empresa con Demo SA de CV')
            ->set('createForm.tax_id', 'DEM260708AA1')
            ->set('createForm.timezone', 'America/Mexico_City')
            ->set('createForm.create_demo', true)
            ->call('create');

        $company = Company::query()->where('tax_id', 'DEM260708AA1')->firstOrFail();
        $this->assertDatabaseHas('company_demo_scenarios', [
            'company_id' => $company->id,
            'requested_by_user_id' => $user->id,
            'status' => CompanyDemoScenario::STATUS_PENDING,
        ]);
        Queue::assertPushed(GenerateCompanyDemoScenarioJob::class, 1);
    }

    public function test_admin_can_update_company_basic_data_and_status(): void
    {
        $adminRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
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

    public function test_company_admin_cannot_suspend_or_cancel_company_status(): void
    {
        $adminRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create();
        $company = Company::factory()->create(['status' => 'active']);

        $user->companies()->attach($company, [
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('companies.index')
            ->assertDontSee('Suspendida')
            ->assertDontSee('Cancelada')
            ->set('editForm.name', $company->name)
            ->set('editForm.legal_name', $company->legal_name)
            ->set('editForm.tax_id', $company->tax_id)
            ->set('editForm.timezone', $company->timezone)
            ->set('editForm.status', 'suspended')
            ->call('update')
            ->assertHasErrors(['editForm.status']);

        $this->assertSame('active', $company->fresh()->status);
    }

    public function test_company_admin_cannot_delete_company(): void
    {
        $adminRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create();
        $company = Company::factory()->create(['status' => 'active']);

        $user->companies()->attach($company, [
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $this->assertFalse($user->can('delete', $company));

        Volt::test('companies.index')
            ->assertDontSee('Eliminar empresa')
            ->assertDontSee('Eliminar');
    }

    public function test_marking_current_company_inactive_clears_current_company_session(): void
    {
        $adminRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
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

    public function test_inactive_company_stays_available_in_company_crud_and_can_be_reactivated(): void
    {
        $adminRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create();
        $activeCompany = Company::factory()->create(['name' => 'Empresa activa']);
        $inactiveCompany = Company::factory()->create([
            'name' => 'Empresa inactiva visible',
            'status' => 'inactive',
        ]);

        $user->companies()->attach($activeCompany, [
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);
        $user->companies()->attach($inactiveCompany, [
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $activeCompany->id]);

        Volt::test('companies.index')
            ->assertSee('Empresa inactiva visible')
            ->call('loadEditForm', $inactiveCompany->id)
            ->set('editForm.name', 'Empresa reactivada')
            ->set('editForm.legal_name', $inactiveCompany->legal_name)
            ->set('editForm.tax_id', $inactiveCompany->tax_id)
            ->set('editForm.timezone', $inactiveCompany->timezone)
            ->set('editForm.status', 'active')
            ->call('update');

        $this->assertDatabaseHas('companies', [
            'id' => $inactiveCompany->id,
            'name' => 'Empresa reactivada',
            'status' => 'active',
        ]);
    }

    public function test_companies_crud_is_available_when_user_only_has_inactive_companies(): void
    {
        $adminRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create();
        $company = Company::factory()->create([
            'name' => 'Empresa solo administrativa',
            'status' => 'inactive',
        ]);

        $user->companies()->attach($company, [
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $this->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Empresa solo administrativa');
    }

    public function test_non_admin_cannot_create_or_update_company(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::SUPERVISOR]);
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

    public function test_global_super_admin_can_open_company_crud_and_edit_any_company_without_membership(): void
    {
        $activeCompany = Company::factory()->create(['name' => 'Empresa activa global', 'status' => 'active']);
        $inactiveCompany = Company::factory()->create(['name' => 'Empresa inactiva global', 'status' => 'inactive']);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin)->withSession(['current_company_id' => $activeCompany->id]);

        $this->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Empresa activa global')
            ->assertSee('Empresa inactiva global');

        Volt::test('companies.index')
            ->call('loadEditForm', $inactiveCompany->id)
            ->set('editForm.name', 'Empresa inactiva editada')
            ->set('editForm.legal_name', $inactiveCompany->legal_name)
            ->set('editForm.tax_id', $inactiveCompany->tax_id)
            ->set('editForm.timezone', $inactiveCompany->timezone)
            ->set('editForm.status', 'inactive')
            ->set('editForm.account_type', 'multi_company')
            ->call('update')
            ->assertSee('Empresa actualizada');

        $this->assertFalse($superAdmin->companies()->whereKey($inactiveCompany->id)->exists());
        $this->assertDatabaseHas('companies', [
            'id' => $inactiveCompany->id,
            'name' => 'Empresa inactiva editada',
            'status' => 'inactive',
        ]);
        $this->assertSame('multi_company', $inactiveCompany->fresh()->customerAccount->account_type);
    }

    public function test_company_status_change_does_not_suspend_customer_account_in_a1_a2(): void
    {
        $activeCompany = Company::factory()->create(['status' => 'active']);
        $managedCompany = Company::factory()->create(['status' => 'active']);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin)->withSession(['current_company_id' => $activeCompany->id]);

        Volt::test('companies.index')
            ->call('loadEditForm', $managedCompany->id)
            ->set('editForm.name', $managedCompany->name)
            ->set('editForm.legal_name', $managedCompany->legal_name)
            ->set('editForm.tax_id', $managedCompany->tax_id)
            ->set('editForm.timezone', $managedCompany->timezone)
            ->set('editForm.status', 'suspended')
            ->set('editForm.account_type', 'single_company')
            ->call('update')
            ->assertSee('Empresa actualizada');

        $managedCompany->refresh();

        $this->assertSame('suspended', $managedCompany->status);
        $this->assertSame('active', $managedCompany->customerAccount->status);
    }

    public function test_global_super_admin_creates_tenant_with_primary_admin_without_self_membership(): void
    {
        $adminRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $currentCompany = Company::factory()->create(['status' => 'active']);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin)->withSession(['current_company_id' => $currentCompany->id]);

        Volt::test('companies.index')
            ->set('createForm.name', 'Tenant Nuevo')
            ->set('createForm.legal_name', 'Tenant Nuevo SA de CV')
            ->set('createForm.tax_id', 'TEN260818AA1')
            ->set('createForm.timezone', 'America/Mexico_City')
            ->set('createForm.account_type', 'multi_company')
            ->set('createForm.admin_name', 'Admin Principal')
            ->set('createForm.admin_email', 'admin.principal@example.test')
            ->set('createForm.admin_password', 'AdminDemo1!')
            ->call('create')
            ->assertSee('Empresa y administrador principal creados')
            ->assertSee('AdminDemo1!');

        $company = Company::query()->where('tax_id', 'TEN260818AA1')->firstOrFail();
        $admin = User::query()->where('email', 'admin.principal@example.test')->firstOrFail();

        $this->assertSame($company->id, session('current_company_id'));
        $this->assertDatabaseHas('company_settings', [
            'company_id' => $company->id,
            'payroll_period_type' => 'biweekly',
        ]);
        $this->assertDatabaseHas('customer_accounts', [
            'id' => $company->customer_account_id,
            'account_type' => 'multi_company',
            'status' => 'active',
        ]);
        $this->assertSame('multi_company', $company->customerAccount->account_type);
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);
        $this->assertDatabaseMissing('company_user', [
            'company_id' => $company->id,
            'user_id' => $superAdmin->id,
        ]);
    }

    public function test_global_super_admin_tenant_creation_blocks_duplicate_admin_email_without_partial_company(): void
    {
        Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        User::factory()->create(['email' => 'duplicado@example.test']);
        $currentCompany = Company::factory()->create(['status' => 'active']);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin)->withSession(['current_company_id' => $currentCompany->id]);

        Volt::test('companies.index')
            ->set('createForm.name', 'Tenant Parcial')
            ->set('createForm.legal_name', 'Tenant Parcial SA de CV')
            ->set('createForm.tax_id', 'PAR260818AA1')
            ->set('createForm.timezone', 'America/Mexico_City')
            ->set('createForm.admin_name', 'Admin Duplicado')
            ->set('createForm.admin_email', 'duplicado@example.test')
            ->set('createForm.admin_password', 'AdminDemo1!')
            ->call('create')
            ->assertHasErrors(['createForm.admin_email']);

        $this->assertDatabaseMissing('companies', [
            'tax_id' => 'PAR260818AA1',
        ]);
        $this->assertDatabaseMissing('customer_accounts', [
            'name' => 'Tenant Parcial',
        ]);
    }

    public function test_global_super_admin_company_list_is_paginated_by_five(): void
    {
        $currentCompany = Company::factory()->create([
            'name' => 'Empresa 00',
            'status' => 'active',
        ]);
        Company::factory()
            ->count(6)
            ->sequence(
                ['name' => 'Empresa 01'],
                ['name' => 'Empresa 02'],
                ['name' => 'Empresa 03'],
                ['name' => 'Empresa 04'],
                ['name' => 'Empresa 05'],
                ['name' => 'Empresa 06'],
            )
            ->create(['status' => 'active']);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin)->withSession(['current_company_id' => $currentCompany->id]);

        Volt::test('companies.index')
            ->assertSee('Empresa 00')
            ->assertSee('Empresa 04')
            ->assertDontSee('Empresa 05')
            ->assertDontSee('Empresa 06')
            ->call('nextPage')
            ->assertSee('Empresa 05')
            ->assertSee('Empresa 06');
    }

    public function test_global_super_admin_can_delete_company_and_empty_customer_account(): void
    {
        $adminRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $customerAccount = CustomerAccount::factory()->create([
            'name' => 'Cuenta a eliminar',
            'account_type' => 'single_company',
            'status' => 'active',
        ]);
        $company = Company::factory()->create([
            'customer_account_id' => $customerAccount->id,
            'name' => 'Empresa Eliminable',
            'status' => 'active',
        ]);
        $admin = User::factory()->create(['email' => 'admin.eliminable@example.test']);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $admin->companies()->attach($company, [
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($superAdmin)->withSession(['current_company_id' => $company->id]);

        Volt::test('companies.index')
            ->call('openDeleteDrawer', $company->id)
            ->set('deleteConfirmation', $company->name)
            ->call('delete')
            ->assertSee('Empresa eliminada');

        $this->assertDatabaseMissing('companies', ['id' => $company->id]);
        $this->assertDatabaseMissing('company_user', ['company_id' => $company->id]);
        $this->assertDatabaseMissing('customer_accounts', ['id' => $customerAccount->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertNull(session('current_company_id'));
    }

    public function test_global_super_admin_delete_company_requires_exact_company_name_confirmation(): void
    {
        $company = Company::factory()->create([
            'name' => 'Empresa Protegida',
            'status' => 'active',
        ]);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin)->withSession(['current_company_id' => $company->id]);

        Volt::test('companies.index')
            ->call('openDeleteDrawer', $company->id)
            ->set('deleteConfirmation', 'ELIMINAR')
            ->call('delete')
            ->assertHasErrors(['deleteConfirmation']);

        $this->assertDatabaseHas('companies', ['id' => $company->id]);
    }

    public function test_global_super_admin_filters_companies_by_name_or_admin_email(): void
    {
        $adminRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $currentCompany = Company::factory()->create([
            'name' => 'Matriz Filtro',
            'status' => 'active',
        ]);
        $targetCompany = Company::factory()->create([
            'name' => 'Constructora Norte',
            'tax_id' => 'CON260818AA1',
            'status' => 'active',
        ]);
        $otherCompany = Company::factory()->create([
            'name' => 'Comercial Sur',
            'status' => 'active',
        ]);
        $targetAdmin = User::factory()->create(['email' => 'admin.norte@example.test']);
        $otherAdmin = User::factory()->create(['email' => 'admin.sur@example.test']);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $targetAdmin->companies()->attach($targetCompany, [
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);
        $otherAdmin->companies()->attach($otherCompany, [
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($superAdmin)->withSession(['current_company_id' => $currentCompany->id]);

        Volt::test('companies.index')
            ->set('companySearch', 'Constructora')
            ->assertSee('Constructora Norte')
            ->assertDontSee('Comercial Sur')
            ->set('companySearch', '')
            ->set('adminEmailSearch', 'admin.sur@example.test')
            ->assertSee('Comercial Sur')
            ->assertSee('admin.sur@example.test')
            ->assertDontSee('Constructora Norte');
    }

    public function test_single_company_user_sees_compact_company_summary_without_directory_filters(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $company = Company::factory()->create([
            'name' => 'Empresa Mono',
            'tax_id' => 'MON260818AA1',
            'status' => 'active',
        ]);
        $user = User::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('companies.index')
            ->assertSee('Empresa actual')
            ->assertSee('Empresa Mono')
            ->assertSee('MON260818AA1')
            ->assertDontSee('Empresas autorizadas')
            ->assertDontSee('Buscar empresa')
            ->assertDontSee('Correo administrador');
    }

    public function test_multi_company_user_sees_company_directory_filters(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $firstCompany = Company::factory()->create([
            'name' => 'Empresa Uno',
            'status' => 'active',
        ]);
        $secondCompany = Company::factory()->create([
            'name' => 'Empresa Dos',
            'status' => 'active',
        ]);
        $user = User::factory()->create();

        foreach ([$firstCompany, $secondCompany] as $company) {
            $user->companies()->attach($company, [
                'role_id' => $role->id,
                'status' => 'active',
                'is_default' => $company->is($firstCompany),
            ]);
        }

        $this->actingAs($user)->withSession(['current_company_id' => $firstCompany->id]);

        Volt::test('companies.index')
            ->assertSee('Empresas autorizadas')
            ->assertSee('Buscar empresa')
            ->assertSee('Correo administrador')
            ->assertSee('Empresa Uno')
            ->assertSee('Empresa Dos')
            ->assertDontSee('Empresa actual');
    }

    public function test_company_switcher_shows_name_only_for_single_company_user(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $company = Company::factory()->create([
            'name' => 'Empresa Unica Sidebar',
            'status' => 'active',
        ]);
        $user = User::factory()->create(['status' => 'active']);

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('companies.company-switcher')
            ->assertSee('Empresa Unica Sidebar')
            ->assertDontSeeHtml('id="company-switcher"');
    }

    public function test_company_switcher_shows_selector_for_multi_company_user_or_super_admin(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $firstCompany = Company::factory()->create([
            'name' => 'Empresa Select Uno',
            'status' => 'active',
        ]);
        $secondCompany = Company::factory()->create([
            'name' => 'Empresa Select Dos',
            'status' => 'active',
        ]);
        $multiCompanyUser = User::factory()->create(['status' => 'active']);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        foreach ([$firstCompany, $secondCompany] as $company) {
            $multiCompanyUser->companies()->attach($company, [
                'role_id' => $role->id,
                'status' => 'active',
                'is_default' => $company->is($firstCompany),
            ]);
        }

        $this->actingAs($multiCompanyUser)->withSession(['current_company_id' => $firstCompany->id]);

        Volt::test('companies.company-switcher')
            ->assertSeeHtml('id="company-switcher"')
            ->assertSee('Empresa Select Uno')
            ->assertSee('Empresa Select Dos');

        $this->actingAs($superAdmin)->withSession(['current_company_id' => $firstCompany->id]);

        Volt::test('companies.company-switcher')
            ->assertSeeHtml('id="company-switcher"')
            ->assertSee('Empresa Select Uno')
            ->assertSee('Empresa Select Dos');
    }
}
