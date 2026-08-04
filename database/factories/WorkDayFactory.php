<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\WorkDay;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkDay>
 */
class WorkDayFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'worker_id' => Worker::factory()->for($company),
            'employment_relationship_id' => EmploymentRelationship::factory()->for($company),
            'center_id' => Center::factory()->for($company),
            'schedule_batch_id' => null,
            'daily_schedule_assignment_id' => null,
            'work_date' => '2026-08-03',
            'timezone' => 'America/Mexico_City',
            'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 0,
            'first_event_at_utc' => null,
            'last_event_at_utc' => null,
            'valid_time_event_ids' => [],
            'active_calculation_id' => null,
            'metadata' => [],
        ];
    }
}
