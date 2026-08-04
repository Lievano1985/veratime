<?php

namespace Database\Factories;

use App\Models\LegalRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalRule>
 */
class LegalRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(3),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => 'daily_limit',
            'status' => LegalRule::STATUS_ACTIVE,
        ];
    }
}
