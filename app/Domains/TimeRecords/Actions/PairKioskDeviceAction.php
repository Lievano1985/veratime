<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\KioskDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PairKioskDeviceAction
{
    /**
     * @return array{device: KioskDevice, device_token: string}
     */
    public function handle(string $pairingCode, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        $code = trim($pairingCode);

        if ($code === '') {
            throw new InvalidArgumentException('El codigo de autorizacion no es valido o ya vencio.');
        }

        return DB::transaction(function () use ($code, $ipAddress, $userAgent): array {
            $device = KioskDevice::query()
                ->with('company')
                ->where('pairing_code_hash', hash('sha256', $code))
                ->lockForUpdate()
                ->first();

            if (! $device || $device->status !== 'pending' || ! $device->pairing_expires_at?->isFuture() || $device->company?->status !== 'active') {
                throw new InvalidArgumentException('El codigo de autorizacion no es valido o ya vencio.');
            }

            $deviceToken = 'vtd_'.Str::random(64);

            $device->forceFill([
                'status' => 'active',
                'pairing_code_hash' => null,
                'pairing_expires_at' => null,
                'paired_at' => now(),
                'device_token_hash' => hash('sha256', $deviceToken),
                'last_seen_at' => now(),
                'last_seen_ip' => $ipAddress,
                'last_seen_user_agent' => Str::limit((string) $userAgent, 1000, ''),
            ])->save();

            return ['device' => $device->refresh()->load(['company', 'center']), 'device_token' => $deviceToken];
        });
    }
}
