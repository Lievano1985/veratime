<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Support\KioskKey;
use InvalidArgumentException;

class ResolveKioskCompanyAction
{
    public function handle(string $kioskKey): Company
    {
        $normalized = KioskKey::normalize($kioskKey);

        if ($normalized === '') {
            throw new InvalidArgumentException('No se pudo activar el kiosco.');
        }

        $setting = CompanySetting::query()
            ->with('company')
            ->where('kiosk_key_hash', KioskKey::hash($normalized))
            ->first();

        if (! $setting?->company || $setting->company->status !== 'active') {
            throw new InvalidArgumentException('No se pudo activar el kiosco.');
        }

        return $setting->company;
    }
}