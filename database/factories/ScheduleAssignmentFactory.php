<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduleAssignment>
 */
class ScheduleAssignmentFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();
        $worker = Worker::factory()->for($company);

        return [
            'company_id' => $company,
            'worker_id' => $worker,
            'employment_relationship_id' => EmploymentRelationship::factory()->for($company)->for($worker),
            'schedule_id' => Schedule::factory()->for($company),
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'status' => 'active',
            'source' => 'web',
            'metadata' => [],
        ];
    }

    public function forCompany(Company $company): static
    {
        $worker = Worker::factory()->for($company);

        return $this->state(fn (): array => [
            'company_id' => $company->id,
            'worker_id' => $worker,
            'employment_relationship_id' => EmploymentRelationship::factory()->for($company)->for($worker),
            'schedule_id' => Schedule::factory()->for($company),
        ]);
    }
}
