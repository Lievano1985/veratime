<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Support\KioskKey;
use Illuminate\Validation\ValidationException;

class UpdateCompanySettingsAction
{
    public function handle(Company $company, array $data): CompanySetting
    {
        if (($data['require_authorized_kiosk_devices'] ?? false)
            && ! $company->kioskDevices()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages([
                'settingsForm.require_authorized_kiosk_devices' => 'Autoriza al menos una terminal antes de exigir terminales autorizadas.',
            ]);
        }

        $kioskKeyHash = filled($data['kiosk_key'] ?? null)
            ? KioskKey::hash((string) $data['kiosk_key'])
            : ($company->setting?->kiosk_key_hash);

        if ($kioskKeyHash && CompanySetting::query()
            ->where('kiosk_key_hash', $kioskKeyHash)
            ->where('company_id', '!=', $company->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'settingsForm.kiosk_key' => 'La clave de kiosco ya esta en uso por otra empresa.',
            ]);
        }

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
            'kiosk_key_hash' => $kioskKeyHash,
            'require_authorized_kiosk_devices' => (bool) ($data['require_authorized_kiosk_devices'] ?? false),
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
