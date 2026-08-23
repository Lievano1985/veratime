<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CustomerAccountSuspensionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_super_admin_can_suspend_and_reactivate_customer_account(): void
    {
        $customerAccount = CustomerAccount::factory()->create([
            'name' => 'Cuenta Demo',
            'status' => 'active',
        ]);
        Company::factory()->create([
            'name' => 'Empresa Demo',
            'customer_account_id' => $customerAccount->id,
            'status' => 'active',
        ]);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin);

        Volt::test('customer-accounts.index')
            ->assertSee('Cuenta Demo')
            ->assertSee('Empresa Demo')
            ->call('updateStatus', $customerAccount->id, 'suspended')
            ->assertSee('Cuenta cliente actualizada');

        $this->assertDatabaseHas('customer_accounts', [
            'id' => $customerAccount->id,
            'status' => 'suspended',
        ]);

        Volt::test('customer-accounts.index')
            ->call('updateStatus', $customerAccount->id, 'active')
            ->assertSee('Cuenta cliente actualizada');

        $this->assertDatabaseHas('customer_accounts', [
            'id' => $customerAccount->id,
            'status' => 'active',
        ]);
    }

    public function test_suspended_customer_account_blocks_current_company_resolution(): void
    {
        [$company, $user] = $this->companyUser(RoleKey::ADMIN_EMPRESA);

        $company->customerAccount()->update(['status' => 'suspended']);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get('/dashboard')
            ->assertForbidden();

        $this->assertFalse(session()->has('current_company_id'));
        $this->assertNull($user->fresh()->defaultCompany());
    }

    public function test_suspended_customer_account_removes_company_from_super_admin_active_selector(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $company->customerAccount()->update(['status' => 'suspended']);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin)
            ->withSession(['current_company_id' => $company->id]);

        $this->get('/dashboard')->assertOk();

        $this->assertFalse(session()->has('current_company_id'));
        $this->assertFalse($superAdmin->activeCompanies()->whereKey($company->id)->exists());
    }

    public function test_non_super_admin_cannot_open_customer_accounts_screen(): void
    {
        [$company, $admin] = $this->companyUser(RoleKey::ADMIN_EMPRESA);

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('customer-accounts.index'))
            ->assertForbidden();
    }

    public function test_company_admin_cannot_update_company_when_customer_account_is_suspended(): void
    {
        [$company, $admin] = $this->companyUser(RoleKey::ADMIN_EMPRESA);

        $company->customerAccount()->update(['status' => 'suspended']);

        $this->assertFalse($admin->can('update', $company->fresh()));
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyUser(string $roleKey): array
    {
        $role = Role::query()->where('key', $roleKey)->firstOrFail();
        $company = Company::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['status' => 'active']);

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return [$company, $user];
    }
}
