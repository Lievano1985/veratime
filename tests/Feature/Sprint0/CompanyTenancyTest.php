<?php

namespace Tests\Feature\Sprint0;

use App\Domains\Tenancy\Actions\SetCurrentCompanyAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_belong_to_company_with_role(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->assertTrue($user->belongsToCompany($company));
        $this->assertSame(RoleKey::ADMIN_EMPRESA, $user->roleKeyForCompany($company));
        $this->assertTrue($user->defaultCompany()->is($company));
    }

    public function test_user_cannot_select_company_from_another_tenant(): void
    {
        $this->expectException(AuthorizationException::class);

        $user = User::factory()->create();
        $company = Company::factory()->create();

        app(SetCurrentCompanyAction::class)->handle($user, $company);
    }

    public function test_global_super_admin_can_select_any_active_company_without_membership(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $otherCompany = Company::factory()->create(['status' => 'active']);
        $inactiveCompany = Company::factory()->create(['status' => 'inactive']);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin);

        app(SetCurrentCompanyAction::class)->handle($superAdmin, $otherCompany);

        $this->assertTrue($superAdmin->belongsToCompany($company));
        $this->assertTrue($superAdmin->belongsToCompany($otherCompany));
        $this->assertFalse($superAdmin->belongsToCompany($inactiveCompany));
        $this->assertSame($otherCompany->id, session('current_company_id'));
        $this->assertSame(RoleKey::SUPER_ADMIN, $superAdmin->roleKeyForCompany($company));
        $this->assertSame(RoleKey::SUPER_ADMIN, $superAdmin->roleKeyForCompany($otherCompany));
        $this->assertNull($superAdmin->roleKeyForCompany($inactiveCompany));
    }

    public function test_global_super_admin_cannot_select_inactive_company(): void
    {
        $this->expectException(AuthorizationException::class);

        $inactiveCompany = Company::factory()->create(['status' => 'inactive']);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        app(SetCurrentCompanyAction::class)->handle($superAdmin, $inactiveCompany);
    }

    public function test_global_super_admin_active_companies_are_all_active_tenants(): void
    {
        $companyA = Company::factory()->create(['name' => 'A Empresa', 'status' => 'active']);
        $companyB = Company::factory()->create(['name' => 'B Empresa', 'status' => 'active']);
        Company::factory()->create(['name' => 'C Inactiva', 'status' => 'inactive']);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $companyIds = $superAdmin->activeCompanies()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$companyA->id, $companyB->id], $companyIds);
        $this->assertTrue($superAdmin->defaultCompany()->is($companyA));
    }

    public function test_current_company_is_stored_only_for_available_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $user->companies()->attach($company, [
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user);

        app(SetCurrentCompanyAction::class)->handle($user, $company);

        $this->assertSame($company->id, session('current_company_id'));
        $this->assertSame($company->id, app(CurrentCompany::class)->id());
    }
}
