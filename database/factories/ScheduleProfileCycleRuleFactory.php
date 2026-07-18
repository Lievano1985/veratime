<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileCycleRule;
use App\Models\ShiftTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleProfileCycleRule>
 */
class ScheduleProfileCycleRuleFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'schedule_profile_id' => ScheduleProfile::factory()->for($company)->state([
                'profile_type' => 'pattern',
                'pattern_mode' => 'cycle',
            ]),
            'cycle_day' => fake()->numberBetween(1, 8),
            'day_type' => 'shift',
            'shift_template_id' => ShiftTemplate::factory()->for($company),
            'metadata' => [],
        ];
    }
}
