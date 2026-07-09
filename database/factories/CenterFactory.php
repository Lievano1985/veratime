<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Center>
 */
class CenterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper(fake()->unique()->bothify('CTR-###')),
            'name' => fake()->city().' Centro',
            'timezone' => 'America/Mexico_City',
            'status' => 'active',
            'address' => null,
            'metadata' => [],
        ];
    }
}
