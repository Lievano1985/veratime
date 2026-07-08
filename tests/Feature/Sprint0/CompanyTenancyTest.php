<?php

namespace Tests\Feature\Sprint0;

use App\Domains\Tenancy\Actions\SetCurrentCompanyAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_belong_to_company_with_role(): void
    {
        $role = Role::factory()->create(['key' => 'admin']);
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->assertTrue($user->belongsToCompany($company));
        $this->assertSame('admin', $user->roleKeyForCompany($company));
        $this->assertTrue($user->defaultCompany()->is($company));
    }

    public function test_user_cannot_select_company_from_another_tenant(): void
    {
        $this->expectException(AuthorizationException::class);

        $user = User::factory()->create();
        $company = Company::factory()->create();

        app(SetCurrentCompanyAction::class)->handle($user, $company);
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
