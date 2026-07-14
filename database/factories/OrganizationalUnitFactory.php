<?php

namespace Database\Factories;

use App\Models\Center;
use App\Models\Company;
use App\Models\OrganizationalUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationalUnit>
 */
class OrganizationalUnitFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'center_id' => Center::factory()->for($company),
            'parent_id' => null,
            'code' => strtoupper(fake()->unique()->bothify('ORG-###')),
            'name' => fake()->words(2, true),
            'type' => 'department',
            'status' => 'active',
            'metadata' => [],
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (): array => [
            'company_id' => $company->id,
            'center_id' => Center::factory()->for($company),
        ]);
    }

    public function forCenter(Center $center): static
    {
        return $this->state(fn (): array => [
            'company_id' => $center->company_id,
            'center_id' => $center->id,
        ]);
    }
}