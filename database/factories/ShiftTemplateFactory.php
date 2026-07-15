<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ShiftTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftTemplate>
 */
class ShiftTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper(fake()->unique()->bothify('TUR-###')),
            'name' => fake()->words(2, true),
            'description' => null,
            'status' => 'active',
            'metadata' => [],
        ];
    }
}
