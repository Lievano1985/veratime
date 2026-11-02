<?php

namespace Tests\Feature\Admin;

use App\Domains\Companies\Actions\UpdateCompanyAction;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountProduct;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\ProductKey;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProductAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_product_seeder_registers_products_without_activating_existing_accounts(): void
    {
        $account = CustomerAccount::query()->create([
            'name' => 'Cuenta existente',
            'account_type' => 'single_company',
            'status' => 'active',
            'metadata' => [],
        ]);

        $this->seed(ProductSeeder::class);

        $this->assertDatabaseHas('products', [
            'key' => ProductKey::TIME,
            'name' => 'VERA Time',
            'status' => Product::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('products', [
            'key' => ProductKey::PAYROLL,
            'status' => Product::STATUS_DRAFT,
        ]);

        $this->assertDatabaseMissing('customer_account_products', [
            'customer_account_id' => $account->id,
        ]);
    }

    public function test_reseeding_products_preserves_account_access_dates_status_and_metadata(): void
    {
        $this->seed(ProductSeeder::class);
        $account = CustomerAccount::factory()->create();
        $product = Product::where('key', ProductKey::TIME)->firstOrFail();
        $access = CustomerAccountProduct::query()->updateOrCreate(
            ['customer_account_id' => $account->id, 'product_id' => $product->id],
            ['status' => CustomerAccountProduct::STATUS_SUSPENDED, 'starts_at' => '2026-01-01',
                'trial_ends_at' => '2026-01-15', 'ends_at' => '2026-12-31', 'metadata' => ['note' => 'Preservar']],
        );
        $before = $access->getRawOriginal();
        $this->seed(ProductSeeder::class);
        $this->assertSame($before, $access->fresh()->getRawOriginal());
    }

    public function test_time_routes_are_blocked_when_customer_account_product_is_suspended(): void
    {
        [$company, $admin] = $this->companyUser(RoleKey::ADMIN_EMPRESA);
        $this->setTimeProductStatus($company, CustomerAccountProduct::STATUS_SUSPENDED);

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('workers.index'))
            ->assertForbidden();
    }

    public function test_editing_company_details_does_not_reactivate_a_suspended_product(): void
    {
        [$company] = $this->companyUser(RoleKey::ADMIN_EMPRESA);
        $access = CustomerAccountProduct::where('customer_account_id', $company->customer_account_id)->firstOrFail();
        $access->update(['status' => CustomerAccountProduct::STATUS_SUSPENDED, 'ends_at' => '2026-12-31', 'metadata' => ['note' => 'Suspensión manual']]);
        $before = $access->getRawOriginal();
        $super = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
        app(UpdateCompanyAction::class)->handle($company, [
            'name' => 'Nombre corregido', 'account_type' => 'single_company',
        ], $super);
        $this->assertSame($before, $access->fresh()->getRawOriginal());
    }

    public function test_time_routes_allow_past_due_product_status(): void
    {
        [$company, $admin] = $this->companyUser(RoleKey::ADMIN_EMPRESA);
        $this->setTimeProductStatus($company, CustomerAccountProduct::STATUS_PAST_DUE);

        $this->actingAs($admin)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('workers.index'))
            ->assertOk();
    }

    public function test_super_admin_can_update_customer_account_product_from_ui(): void
    {
        [$company] = $this->companyUser(RoleKey::ADMIN_EMPRESA);
        $timeProduct = Product::query()->where('key', ProductKey::TIME)->firstOrFail();
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin);

        Volt::test('customer-accounts.index')
            ->call('openProductPanel', $company->customer_account_id)
            ->assertSet('productForm.product_id', $timeProduct->id)
            ->set('productForm.status', CustomerAccountProduct::STATUS_SUSPENDED)
            ->set('productForm.starts_at', '2026-09-01')
            ->set('productForm.trial_ends_at', '')
            ->set('productForm.ends_at', '')
            ->call('saveProduct')
            ->assertHasNoErrors()
            ->assertSee('Producto contratado actualizado.');

        $this->assertDatabaseHas('customer_account_products', [
            'customer_account_id' => $company->customer_account_id,
            'product_id' => $timeProduct->id,
            'status' => CustomerAccountProduct::STATUS_SUSPENDED,
        ]);
    }

    /**
     * @return array{0: Company, 1: User}
     */
    private function companyUser(string $roleKey): array
    {
        $role = Role::query()->where('key', $roleKey)->firstOrFail();
        $company = Company::factory()->create(['status' => 'active']);
        $company->setting()->create(Company::defaultSettings());
        $user = User::factory()->create(['status' => 'active']);

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return [$company->refresh(), $user];
    }

    private function setTimeProductStatus(Company $company, string $status): void
    {
        $timeProduct = Product::query()->where('key', ProductKey::TIME)->firstOrFail();

        CustomerAccountProduct::query()
            ->where('customer_account_id', $company->customer_account_id)
            ->where('product_id', $timeProduct->id)
            ->update(['status' => $status]);
    }
}
