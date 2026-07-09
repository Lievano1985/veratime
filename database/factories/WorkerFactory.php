<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Worker>
 */
class WorkerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'employee_code' => strtoupper(fake()->unique()->bothify('EMP-####')),
            'full_name' => fake()->name(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'curp' => null,
            'rfc' => strtoupper(fake()->unique()->bothify('???######???')),
            'status' => 'active',
            'source' => 'web',
            'external_id' => null,
            'metadata' => [],
        ];
    }
}
