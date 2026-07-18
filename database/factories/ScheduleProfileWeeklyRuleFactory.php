<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileWeeklyRule;
use App\Models\ShiftTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleProfileWeeklyRule>
 */
class ScheduleProfileWeeklyRuleFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'schedule_profile_id' => ScheduleProfile::factory()->for($company),
            'day_of_week' => fake()->numberBetween(1, 7),
            'day_type' => 'shift',
            'shift_template_id' => ShiftTemplate::factory()->for($company),
            'metadata' => [],
        ];
    }
}
