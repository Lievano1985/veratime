<?php

namespace Tests\Feature\Sprint0;

use App\Models\Role;
use App\Support\RoleKey;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_creates_initial_system_roles(): void
    {
        $this->seed(RoleSeeder::class);

        $expectedRoles = [
            RoleKey::OWNER,
            RoleKey::ADMIN,
            RoleKey::RH,
            RoleKey::SUPERVISOR,
            RoleKey::PAYROLL,
            RoleKey::COMPLIANCE,
        ];

        foreach ($expectedRoles as $role) {
            $this->assertDatabaseHas(Role::class, [
                'key' => $role,
                'is_system' => true,
            ]);
        }

        $this->assertDatabaseMissing(Role::class, [
            'key' => 'hr',
        ]);
    }
}