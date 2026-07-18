<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileFlexibleRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleProfileFlexibleRule>
 */
class ScheduleProfileFlexibleRuleFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'schedule_profile_id' => ScheduleProfile::factory()->for($company)->state([
                'profile_type' => 'flexible',
                'pattern_mode' => null,
            ]),
            'day_of_week' => fake()->numberBetween(1, 7),
            'day_type' => 'work',
            'required_minutes' => 480,
            'window_start_local_time' => '07:00:00',
            'window_end_local_time' => '20:00:00',
            'window_start_day_offset' => 0,
            'window_end_day_offset' => 0,
            'metadata' => [],
        ];
    }
}
