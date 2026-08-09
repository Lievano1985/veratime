<?php

namespace Database\Factories;

use App\Models\AttendancePeriod;
use App\Models\Center;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendancePeriod>
 */
class AttendancePeriodFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'center_id' => Center::factory()->for($company),
            'scope_type' => AttendancePeriod::SCOPE_CENTER,
            'name' => fake()->words(3, true),
            'period_start' => now()->startOfWeek()->toDateString(),
            'period_end' => now()->startOfWeek()->addDays(6)->toDateString(),
            'timezone' => 'America/Mexico_City',
            'status' => AttendancePeriod::STATUS_OPEN,
            'notes' => null,
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
            'timezone' => $center->timezone,
        ]);
    }
}
