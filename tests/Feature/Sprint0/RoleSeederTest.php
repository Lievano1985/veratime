<?php

namespace Tests\Feature\Sprint0;

use App\Models\Role;
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
            'owner',
            'admin',
            'hr',
            'supervisor',
            'payroll',
            'compliance',
        ];

        foreach ($expectedRoles as $role) {
            $this->assertDatabaseHas(Role::class, [
                'key' => $role,
                'is_system' => true,
            ]);
        }
    }
}
