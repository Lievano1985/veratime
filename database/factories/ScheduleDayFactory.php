<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Schedule;
use App\Models\ScheduleDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleDay>
 */
class ScheduleDayFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'company_id' => $company->id,
            'schedule_id' => Schedule::factory()->for($company),
            'day_of_week' => fake()->numberBetween(0, 6),
            'is_working_day' => true,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'crosses_midnight' => false,
        ];
    }
}
