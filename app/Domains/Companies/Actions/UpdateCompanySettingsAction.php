<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use App\Models\CompanySetting;

class UpdateCompanySettingsAction
{
    public function handle(Company $company, array $data): CompanySetting
    {
        $settings = array_merge(Company::defaultSettings(), [
            'payroll_period_type' => $data['payroll_period_type'],
            'default_timezone' => $data['default_timezone'],
            'default_closure_day' => $data['default_closure_day'] ?? null,
            'allow_worker_corrections' => (bool) ($data['allow_worker_corrections'] ?? false),
            'require_pin_for_kiosk' => (bool) ($data['require_pin_for_kiosk'] ?? false),
            'require_pin_for_confirmation' => (bool) ($data['require_pin_for_confirmation'] ?? false),
            'metadata' => [],
        ]);

        $company->forceFill([
            'timezone' => $settings['default_timezone'],
        ])->save();

        return $company->setting()->updateOrCreate(
            ['company_id' => $company->id],
            $settings,
        );
    }
}
