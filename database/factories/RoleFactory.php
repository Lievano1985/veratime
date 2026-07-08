<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'key' => str($name)->slug('_')->toString(),
            'name' => $name,
            'description' => fake()->sentence(),
            'is_system' => false,
        ];
    }
}
