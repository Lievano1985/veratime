<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyScheduleSegment>
 */
class DailyScheduleSegmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'daily_schedule_assignment_id' => DailyScheduleAssignment::factory(),
            'segment_order' => 1,
            'segment_type' => 'work',
            'timing_mode' => 'fixed',
            'start_local_time' => '08:00:00',
            'end_local_time' => '16:00:00',
            'start_day_offset' => 0,
            'end_day_offset' => 0,
            'duration_minutes' => null,
            'is_paid' => true,
            'metadata' => [],
        ];
    }
}
