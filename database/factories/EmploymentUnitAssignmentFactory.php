<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentUnitAssignment>
 */
class EmploymentUnitAssignmentFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();
        $relationship = EmploymentRelationship::factory()->forCompany($company);

        return [
            'company_id' => $company,
            'employment_relationship_id' => $relationship,
            'organizational_unit_id' => OrganizationalUnit::factory()->forCompany($company),
            'assignment_type' => 'primary',
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