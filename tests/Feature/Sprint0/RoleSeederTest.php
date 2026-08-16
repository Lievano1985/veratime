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

    public function test_database_seeder_creates_super_admin_user(): void
    {
        $this->seed(DatabaseSeeder::class);

        $superAdmin = User::query()
            ->where('email', 'superadmin@veratime.local')
            ->firstOrFail();

        $company = $superAdmin->defaultCompany();

        $this->assertNotNull($company);
        $this->assertSame(RoleKey::SUPER_ADMIN, $superAdmin->roleKeyForCompany($company));
    }
}
