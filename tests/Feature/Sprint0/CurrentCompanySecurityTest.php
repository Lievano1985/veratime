<?php

namespace Tests\Feature\Sprint0;

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentCompanySecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_company_ignores_manipulated_session_for_unauthorized_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id]);

        $this->assertNull(app(CurrentCompany::class)->get());
        $this->assertFalse(session()->has('current_company_id'));
    }

    public function test_inactive_company_user_relation_does_not_resolve_current_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->companies()->attach($company, [
            'status' => 'inactive',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id]);

        $this->assertNull($user->defaultCompany());
        $this->assertNull(app(CurrentCompany::class)->get());
        $this->assertFalse(session()->has('current_company_id'));
    }

    public function test_inactive_company_user_relation_cannot_access_protected_operational_screen(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->companies()->attach($company, [
            'status' => 'inactive',
            'is_default' => true,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertForbidden();
    }

    public function test_inactive_company_does_not_resolve_current_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['status' => 'inactive']);

        $user->companies()->attach($company, [
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id]);

        $this->assertNull($user->defaultCompany());
        $this->assertNull(app(CurrentCompany::class)->get());
        $this->assertFalse(session()->has('current_company_id'));
    }

    public function test_inactive_company_cannot_access_protected_operational_screen(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create(['status' => 'inactive']);

        $user->companies()->attach($company, [
            'status' => 'active',
            'is_default' => true,
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get('/dashboard');

        $response->assertForbidden();
        $this->assertFalse(session()->has('current_company_id'));
    }

    public function test_super_admin_without_active_companies_can_access_dashboard(): void
    {
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Sin empresas activas')
            ->assertSee('Empresas')
            ->assertDontSee('Centros')
            ->assertDontSee('Descansos obligatorios');
    }

    public function test_super_admin_without_active_companies_is_redirected_from_operational_routes_to_companies(): void
    {
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $this->actingAs($superAdmin)
            ->get('/centers')
            ->assertRedirect(route('companies.index'));
    }
}
