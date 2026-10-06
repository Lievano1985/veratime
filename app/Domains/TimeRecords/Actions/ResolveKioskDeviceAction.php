<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\KioskDevice;
use Illuminate\Support\Str;

class ResolveKioskDeviceAction
{
    public function handle(?string $deviceToken, ?string $ipAddress = null, ?string $userAgent = null): ?KioskDevice
    {
        if (blank($deviceToken)) {
            return null;
        }

        $device = KioskDevice::query()
            ->with(['company.setting', 'center'])
            ->where('device_token_hash', hash('sha256', $deviceToken))
            ->where('status', 'active')
            ->first();

        if (! $device || $device->company?->status !== 'active' || ($device->center && $device->center->status !== 'active')) {
            return null;
        }

        $device->forceFill([
            'last_seen_at' => now(),
            'last_seen_ip' => $ipAddress,
            'last_seen_user_agent' => Str::limit((string) $userAgent, 1000, ''),
        ])->save();

        return $device;
    }
}
