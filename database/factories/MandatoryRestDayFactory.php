<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MandatoryRestDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MandatoryRestDay>
 */
class MandatoryRestDayFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Descanso obligatorio',
            'date' => now()->toDateString(),
            'type' => 'company_internal',
            'scope' => 'company',
            'state_code' => null,
            'source_reference' => 'Referencia demo opcional',
            'capture_source' => 'manual',
            'status' => 'active',
            'metadata' => [],
        ];
    }

    public function national(): static
    {
        return $this->state(fn (): array => [
            'company_id' => null,
            'type' => 'legal_mandatory',
            'scope' => 'national',
            'state_code' => null,
        ]);
    }

    public function global(): static
    {
        return $this->national();
    }

    public function stateScoped(string $stateCode = 'MX-JAL'): static
    {
        return $this->state(fn (): array => [
            'company_id' => null,
            'type' => 'electoral',
            'scope' => 'state',
            'state_code' => $stateCode,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => 'inactive',
        ]);
    }
}
