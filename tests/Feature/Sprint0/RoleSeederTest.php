<?php

namespace Tests\Feature\Sprint0;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_canonical_system_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $expectedRoles = [
            RoleKey::SUPER_ADMIN,
            RoleKey::ADMIN_EMPRESA,
            RoleKey::RH_ADMIN,
            RoleKey::RH_OPERATIVO,
            RoleKey::SUPERVISOR,
            RoleKey::TRABAJADOR,
        ];

        foreach ($expectedRoles as $role) {
            $this->assertDatabaseHas(Role::class, [
                'key' => $role,
                'is_system' => true,
            ]);
        }

        foreach (['owner', 'admin', 'rh', 'hr'] as $legacyRole) {
            $this->assertDatabaseMissing(Role::class, ['key' => $legacyRole]);
        }
    }

    public function test_database_seeder_preserves_explicit_super_admin_without_creating_demo_companies(): void
    {
        $superAdmin = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
        $passwordHash = $superAdmin->password;
        $this->seed(DatabaseSeeder::class);
        $company = $superAdmin->defaultCompany();

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertSame(RoleKey::SUPER_ADMIN, $superAdmin->global_role);
        $this->assertNull($company);
        $this->assertSame($passwordHash, $superAdmin->fresh()->password);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseMissing('company_user', [
            'user_id' => $superAdmin->id,
        ]);
    }
}
