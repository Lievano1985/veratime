<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleProfileAssignment>
 */
class ScheduleProfileAssignmentFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'schedule_profile_id' => ScheduleProfile::factory()->for($company),
            'assignment_scope' => 'company',
            'center_id' => null,
            'organizational_unit_id' => null,
            'employment_relationship_id' => null,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'status' => 'active',
            'source' => 'manual',
            'reason' => null,
            'replaced_by_id' => null,
            'created_by' => null,
            'metadata' => [],
        ];
    }
}
