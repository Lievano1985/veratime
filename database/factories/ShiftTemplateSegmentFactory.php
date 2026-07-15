<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ShiftTemplate;
use App\Models\ShiftTemplateSegment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftTemplateSegment>
 */
class ShiftTemplateSegmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'shift_template_id' => ShiftTemplate::factory(),
            'segment_type' => 'work',
            'timing_mode' => 'fixed',
            'start_local_time' => '08:00:00',
            'end_local_time' => '16:00:00',
            'start_day_offset' => 0,
            'end_day_offset' => 0,
            'duration_minutes' => null,
            'is_paid' => true,
            'is_required' => true,
            'sort_order' => 1,
            'metadata' => [],
        ];
    }
}
