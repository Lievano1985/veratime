<?php

namespace App\Domains\Workers\Actions;

use App\Models\MobileDeviceBinding;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class RevokeMobileDeviceBindingAction
{
    public function handle(MobileDeviceBinding $binding, User $actor): MobileDeviceBinding
    {
        Gate::forUser($actor)->authorize('revoke', $binding);

        return DB::transaction(function () use ($binding, $actor): MobileDeviceBinding {
            $binding = MobileDeviceBinding::query()->lockForUpdate()->findOrFail($binding->id);

            if ($binding->status !== MobileDeviceBinding::STATUS_ACTIVE) {
                throw new InvalidArgumentException('El dispositivo ya no está activo.');
            }

            $binding->forceFill([
                'status' => MobileDeviceBinding::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_by_user_id' => $actor->id,
            ])->save();

            return $binding->refresh();
        });
    }
}
