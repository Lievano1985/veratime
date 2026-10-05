<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\KioskTerminalAccessRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SetKioskEnrollmentKeyAction
{
    public function handle(Company $company, User $actor, string $key): CompanySetting
    {
        Gate::forUser($actor)->authorize('update', $company);

        return DB::transaction(function () use ($company, $key): CompanySetting {
            $settings = $company->setting()->lockForUpdate()->firstOrFail();
            $identifier = $settings->kiosk_enrollment_identifier;

            $settings->forceFill([
                'kiosk_enrollment_identifier' => $this->isCurrentIdentifier($identifier) ? $identifier : $this->newIdentifier(),
                'kiosk_enrollment_key_hash' => Hash::make($key),
            ])->save();

            KioskTerminalAccessRequest::query()
                ->where('company_id', $company->id)
                ->where('status', 'pending')
                ->update(['status' => 'expired', 'updated_at' => now()]);

            return $settings->refresh();
        });
    }

    private function newIdentifier(): string
    {
        do {
            $identifier = 'VT-'.Str::upper(Str::random(6));
        } while (CompanySetting::query()->where('kiosk_enrollment_identifier', $identifier)->exists());

        return $identifier;
    }

    private function isCurrentIdentifier(?string $identifier): bool
    {
        return is_string($identifier) && preg_match('/^VT-[A-Z0-9]{6}$/', $identifier) === 1;
    }
}
