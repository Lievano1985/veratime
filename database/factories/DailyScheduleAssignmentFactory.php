<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyScheduleAssignment>
 */
class DailyScheduleAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'schedule_batch_id' => ScheduleBatch::factory(),
            'employment_relationship_id' => EmploymentRelationship::factory(),
            'work_date' => '2026-08-03',
            'day_type' => 'unassigned',
            'timezone' => 'America/Mexico_City',
            'source_type' => 'manual',
            'source_reference' => null,
            'metadata' => [],
        ];
    }
}
