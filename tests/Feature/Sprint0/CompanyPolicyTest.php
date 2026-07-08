<?php

namespace Tests\Feature\Sprint0;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_view_company_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $this->assertFalse($user->can('view', $company));
    }

    public function test_company_admin_can_update_their_company(): void
    {
        $role = Role::factory()->create(['key' => 'admin']);
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->assertTrue($user->can('view', $company));
        $this->assertTrue($user->can('update', $company));
    }

    public function test_non_admin_role_cannot_update_a_company(): void
    {
        $role = Role::factory()->create(['key' => 'supervisor']);
        $user = User::factory()->create();
        $company = Company::factory()->create();

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->assertTrue($user->can('view', $company));
        $this->assertFalse($user->can('update', $company));
    }

    public function test_inactive_company_cannot_be_viewed_or_updated(): void
    {
        $role = Role::factory()->create(['key' => 'admin']);
        $user = User::factory()->create();
        $company = Company::factory()->create(['status' => 'inactive']);

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->assertFalse($user->can('view', $company));
        $this->assertFalse($user->can('update', $company));
    }
}
