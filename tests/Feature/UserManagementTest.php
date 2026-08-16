<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_company_manager_can_create_company_user(): void
    {
        [$company, $admin] = $this->companyUser(RoleKey::ADMIN_EMPRESA);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('users.index')
            ->call('openCreatePanel')
            ->set('form.name', 'Demo RH Usuario')
            ->set('form.email', 'nuevo.rh@veratime.local')
            ->set('form.password', 'Temporal123')
            ->set('form.role_key', RoleKey::RH_OPERATIVO)
            ->set('form.status', 'active')
            ->call('create')
            ->assertSee('Usuario creado');

        $user = User::query()->where('email', 'nuevo.rh@veratime.local')->firstOrFail();

        $this->assertTrue(Hash::check('Temporal123', $user->password));
        $this->assertSame(RoleKey::RH_OPERATIVO, $user->roleKeyForCompany($company));
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_rh_admin_cannot_assign_admin_empresa_role(): void
    {
        [$company, $rh] = $this->companyUser(RoleKey::RH_ADMIN);

        $this->actingAs($rh)->withSession(['current_company_id' => $company->id]);

        Volt::test('users.index')
            ->set('form.name', 'Admin no permitido')
            ->set('form.email', 'admin.no@veratime.local')
            ->set('form.password', 'Temporal123')
            ->set('form.role_key', RoleKey::ADMIN_EMPRESA)
            ->set('form.status', 'active')
            ->call('create')
            ->assertHasErrors(['form.role_key']);

        $this->assertDatabaseMissing('users', ['email' => 'admin.no@veratime.local']);
    }

    public function test_super_admin_can_create_company_admin_user(): void
    {
        [$company, $superAdmin] = $this->companyUser(RoleKey::SUPER_ADMIN);

        $this->actingAs($superAdmin)->withSession(['current_company_id' => $company->id]);

        Volt::test('users.index')
            ->call('openCreatePanel')
            ->set('form.name', 'Admin Empresa Nuevo')
            ->set('form.email', 'admin.empresa.nuevo@veratime.local')
            ->set('form.password', 'Temporal123')
            ->set('form.role_key', RoleKey::ADMIN_EMPRESA)
            ->set('form.status', 'active')
            ->call('create')
            ->assertSee('Usuario creado');

        $user = User::query()->where('email', 'admin.empresa.nuevo@veratime.local')->firstOrFail();

        $this->assertSame(RoleKey::ADMIN_EMPRESA, $user->roleKeyForCompany($company));
    }

    public function test_supervisor_cannot_access_user_management(): void
    {
        [$company, $supervisor] = $this->companyUser(RoleKey::SUPERVISOR);

        $this->actingAs($supervisor)->withSession(['current_company_id' => $company->id]);

        $this->get(route('users.index'))->assertForbidden();
    }

    public function test_user_from_another_company_cannot_be_edited(): void
    {
        [$company, $admin] = $this->companyUser(RoleKey::ADMIN_EMPRESA);
        [$otherCompany, $otherUser] = $this->companyUser(RoleKey::RH_ADMIN);

        $this->assertFalse($otherUser->belongsToCompany($company));
        $this->assertTrue($otherUser->belongsToCompany($otherCompany));

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        $this->expectException(ModelNotFoundException::class);

        Volt::test('users.index')->call('openEditPanel', $otherUser->id);
    }

    public function test_admin_can_reset_company_user_password(): void
    {
        [$company, $admin] = $this->companyUser(RoleKey::ADMIN_EMPRESA);
        [, $target] = $this->companyUser(RoleKey::RH_ADMIN, $company);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('users.index')
            ->call('openResetPanel', $target->id)
            ->set('resetForm.password', 'NuevaTemporal123')
            ->call('resetPassword')
            ->assertSee('Contraseña actualizada');

        $this->assertTrue(Hash::check('NuevaTemporal123', $target->refresh()->password));
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyUser(string $roleKey, ?Company $company = null): array
    {
        $role = Role::query()->where('key', $roleKey)->firstOrFail();
        $company ??= Company::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['status' => 'active']);

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return [$company, $user];
    }
}
