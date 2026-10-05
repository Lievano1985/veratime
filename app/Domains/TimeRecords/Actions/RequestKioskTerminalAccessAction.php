<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\CompanySetting;
use App\Models\KioskTerminalAccessRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RequestKioskTerminalAccessAction
{
    /** @return array{request: KioskTerminalAccessRequest, request_secret: string} */
    public function handle(string $identifier, string $key, string $name, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        return DB::transaction(function () use ($identifier, $key, $name, $ipAddress, $userAgent): array {
            $settings = CompanySetting::query()
                ->with('company')
                ->lockForUpdate()
                ->where('kiosk_enrollment_identifier', Str::upper(trim($identifier)))
                ->first();

            if (! $settings || ! $settings->company || $settings->company->status !== 'active' || blank($settings->kiosk_enrollment_key_hash) || ! Hash::check($key, $settings->kiosk_enrollment_key_hash)) {
                throw new InvalidArgumentException('No se pudo validar el codigo de empresa o la clave de solicitud.');
            }

            $pendingCount = KioskTerminalAccessRequest::query()
                ->where('company_id', $settings->company_id)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->count();

            if ($pendingCount >= 25) {
                throw new InvalidArgumentException('No se pudo registrar la solicitud de esta terminal. Intenta mas tarde.');
            }

            $requestSecret = 'ktr_'.Str::random(64);

            $request = KioskTerminalAccessRequest::query()->create([
                'company_id' => $settings->company_id,
                'public_id' => (string) Str::uuid(),
                'request_secret_hash' => hash('sha256', $requestSecret),
                'requested_name' => trim($name),
                'status' => 'pending',
                'expires_at' => now()->addMinutes(15),
                'requested_ip' => $ipAddress,
                'requested_user_agent' => Str::limit((string) $userAgent, 1000, ''),
            ]);

            return ['request' => $request, 'request_secret' => $requestSecret];
        });
    }
}
