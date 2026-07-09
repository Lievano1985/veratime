<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySetting>
 */
class CompanySettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'payroll_period_type' => 'biweekly',
            'default_timezone' => 'America/Mexico_City',
            'default_closure_day' => 15,
            'allow_worker_corrections' => false,
            'require_pin_for_kiosk' => true,
            'require_pin_for_confirmation' => true,
            'metadata' => [],
        ];
    }
}
