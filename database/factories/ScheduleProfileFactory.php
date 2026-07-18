<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ScheduleProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleProfile>
 */
class ScheduleProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper(fake()->unique()->bothify('PER-###')),
            'name' => fake()->words(2, true),
            'description' => null,
            'profile_type' => 'pattern',
            'pattern_mode' => 'weekly',
            'status' => 'active',
            'metadata' => [],
        ];
    }

    public function calendar(): static
    {
        return $this->state(fn (): array => [
            'profile_type' => 'calendar',
            'pattern_mode' => null,
        ]);
    }
}
