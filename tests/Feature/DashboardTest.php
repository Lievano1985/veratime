<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user->companies()->attach($company, [
            'status' => 'active',
            'is_default' => true,
            'role_id' => $role->id,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);
    }

    public function test_authenticated_users_without_active_company_cannot_visit_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertForbidden();
    }
}
