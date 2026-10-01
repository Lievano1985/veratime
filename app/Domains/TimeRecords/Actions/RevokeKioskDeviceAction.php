<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\KioskDevice;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RevokeKioskDeviceAction
{
    public function handle(KioskDevice $device, User $actor): KioskDevice
    {
        Gate::forUser($actor)->authorize('update', $device);

        $device->forceFill([
            'status' => 'revoked',
            'pairing_code_hash' => null,
            'pairing_expires_at' => null,
            'device_token_hash' => null,
            'revoked_at' => now(),
            'revoked_by_user_id' => $actor->id,
        ])->save();

        return $device->refresh();
    }
}
