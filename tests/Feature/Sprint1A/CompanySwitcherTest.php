<?php

namespace Tests\Feature\Sprint1A;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CompanySwitcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_switcher_changes_only_to_authorized_active_company(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create();
        $firstCompany = Company::factory()->create(['name' => 'Primera empresa']);
        $secondCompany = Company::factory()->create(['name' => 'Segunda empresa']);

        $user->companies()->attach($firstCompany, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);
        $user->companies()->attach($secondCompany, [
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $firstCompany->id]);

        $this->from(route('companies.index'));

        Volt::test('companies.company-switcher')
            ->set('companyId', $secondCompany->id)
            ->assertRedirect(route('companies.index'));

        $this->assertSame($secondCompany->id, session('current_company_id'));
    }

    public function test_company_switcher_rejects_foreign_or_inactive_company(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $inactiveCompany = Company::factory()->create(['status' => 'inactive']);
        $foreignCompany = Company::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);
        $user->companies()->attach($inactiveCompany, [
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('companies.company-switcher')
            ->assertSee($company->name)
            ->assertDontSee($inactiveCompany->name)
            ->assertDontSee($foreignCompany->name);

        Volt::test('companies.company-switcher')
            ->set('companyId', $foreignCompany->id)
            ->assertForbidden();

        Volt::test('companies.company-switcher')
            ->set('companyId', $inactiveCompany->id)
            ->assertForbidden();
    }
}
