<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\DB;

class UpdateCompanySettingsAction
{
    public function handle(Company $company, array $data): CompanySetting
    {
        return DB::transaction(function () use ($company, $data): CompanySetting {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            $metadata = $company->setting()->first()?->metadata ?? [];
            $settings = array_merge(Company::defaultSettings(), [
                'payroll_period_type' => $data['payroll_period_type'],
                'default_timezone' => $data['default_timezone'],
                'default_closure_day' => $data['default_closure_day'] ?? null,
                'work_days_auto_refresh_time' => blank($data['work_days_auto_refresh_time'] ?? null)
                    ? null
                    : (string) $data['work_days_auto_refresh_time'],
                'late_arrival_tolerance_minutes' => max(0, (int) ($data['late_arrival_tolerance_minutes'] ?? 0)),
                'early_departure_tolerance_minutes' => max(0, (int) ($data['early_departure_tolerance_minutes'] ?? 0)),
                'allow_worker_corrections' => (bool) ($data['allow_worker_corrections'] ?? false),
                'require_pin_for_kiosk' => (bool) ($data['require_pin_for_kiosk'] ?? false),
                'require_pin_for_confirmation' => (bool) ($data['require_pin_for_confirmation'] ?? false),
                'metadata' => $metadata,
            ]);

            $company->forceFill([
                'timezone' => $settings['default_timezone'],
            ])->save();

            return $company->setting()->updateOrCreate(
                ['company_id' => $company->id],
                $settings,
            );
        });
    }
}
