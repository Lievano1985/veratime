<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Company;
use App\Models\OperationalScopeAssignment;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalScopeAssignment>
 */
class OperationalScopeAssignmentFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();
        $role = Role::query()->firstOrCreate(
            ['key' => RoleKey::SUPERVISOR],
            ['name' => 'Supervisor', 'description' => 'Rol supervisor', 'is_system' => true],
        );
        $user = User::factory();

        return [
            'company_id' => $company,
            'user_id' => $user,
            'center_id' => Center::factory()->for($company),
            'organizational_unit_id' => null,
            'responsibility_type' => 'supervisor',
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'status' => 'active',
            'source' => 'manual',
            'reason' => null,
            'replaced_by_id' => null,
            'created_by' => null,
            'metadata' => [],
        ];
    }
}