<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileOnCallRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleProfileOnCallRule>
 */
class ScheduleProfileOnCallRuleFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'schedule_profile_id' => ScheduleProfile::factory()->for($company)->state([
                'profile_type' => 'on_call',
                'pattern_mode' => null,
            ]),
            'day_of_week' => fake()->numberBetween(1, 7),
            'day_type' => 'on_call',
            'availability_start_local_time' => '06:00:00',
            'availability_end_local_time' => '22:00:00',
            'availability_start_day_offset' => 0,
            'availability_end_day_offset' => 0,
            'max_work_minutes' => 480,
            'metadata' => [],
        ];
    }
}
