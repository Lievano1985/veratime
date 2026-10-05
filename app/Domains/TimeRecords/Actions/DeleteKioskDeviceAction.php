<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\KioskDevice;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DeleteKioskDeviceAction
{
    public function handle(KioskDevice $device, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $device);

        // A soft delete immediately invalidates the terminal while retaining
        // its technical identity for any historical kiosk event metadata.
        $device->delete();
    }
}
