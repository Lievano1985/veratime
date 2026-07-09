<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\LaborCondition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaborCondition>
 */
class LaborConditionFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'company_id' => $company->id,
            'employment_relationship_id' => EmploymentRelationship::factory()->forCompany($company),
            'schedule_id' => null,
            'work_modality' => 'onsite',
            'weekly_hours' => 48,
            'rest_day_of_week' => 0,
            'policy_id' => null,
            'effective_from' => now()->subMonth()->toDateString(),
            'effective_to' => null,
            'status' => 'active',
            'metadata' => [],
        ];
    }
}
