<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Schedule;
use App\Models\ScheduleBreak;
use App\Models\ScheduleDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleBreak>
 */
class ScheduleBreakFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory()->create();
        $schedule = Schedule::factory()->for($company)->create();

        return [
            'company_id' => $company->id,
            'schedule_day_id' => ScheduleDay::factory()->for($company)->for($schedule),
            'name' => 'Comida',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'duration_minutes' => 60,
            'is_paid' => false,
            'is_required' => true,
        ];
    }
}
