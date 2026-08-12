<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkDayCalculation>
 */
class WorkDayCalculationFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'work_day_id' => WorkDay::factory()->for($company),
            'version' => 1,
            'status' => WorkDayCalculation::STATUS_ACTIVE,
            'calculated_at' => now('UTC'),
            'generated_by_type' => WorkDayCalculation::GENERATED_BY_SYSTEM,
            'generated_by_id' => null,
            'reason' => null,
            'total_work_minutes' => 480,
            'ordinary_minutes' => 480,
            'night_minutes' => 0,
            'overtime_minutes' => 0,
            'overtime_double_minutes' => 0,
            'overtime_triple_minutes' => 0,
            'break_minutes' => 0,
            'paid_break_minutes' => 0,
            'sunday_minutes' => 0,
            'mandatory_rest_minutes' => 0,
            'classification' => WorkDayCalculation::CLASSIFICATION_PENDING,
            'rules_snapshot' => [],
            'inputs_snapshot' => [],
            'result_snapshot' => [],
            'explanation' => [],
        ];
    }
}
