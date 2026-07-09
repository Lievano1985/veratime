<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentRelationship>
 */
class EmploymentRelationshipFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'worker_id' => Worker::factory()->for($company),
            'center_id' => Center::factory()->for($company),
            'position_name' => fake()->jobTitle(),
            'started_at' => now()->subMonth()->toDateString(),
            'ended_at' => null,
            'status' => 'active',
            'source' => 'web',
            'external_id' => null,
            'metadata' => [],
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (): array => [
            'company_id' => $company->id,
            'worker_id' => Worker::factory()->for($company),
            'center_id' => Center::factory()->for($company),
        ]);
    }
}
