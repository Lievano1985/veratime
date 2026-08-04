<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\LegalParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalParameter>
 */
class LegalParameterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => null,
            'code' => fake()->unique()->slug(3),
            'value' => ['minutes' => 480],
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'status' => LegalParameter::STATUS_ACTIVE,
            'source_reference' => 'SRC-001',
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (): array => [
            'company_id' => $company->id,
        ]);
    }
}
