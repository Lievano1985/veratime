<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'legal_name' => fake()->company().' SA de CV',
            'tax_id' => strtoupper(fake()->unique()->bothify('???######???')),
            'timezone' => 'America/Mexico_City',
            'status' => 'active',
            'settings' => [],
        ];
    }
}
