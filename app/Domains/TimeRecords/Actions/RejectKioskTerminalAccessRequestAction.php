<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\KioskDevice;
use App\Models\KioskTerminalAccessRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class RejectKioskTerminalAccessRequestAction
{
    public function handle(KioskTerminalAccessRequest $request, User $actor): KioskTerminalAccessRequest
    {
        Gate::forUser($actor)->authorize('create', [KioskDevice::class, $request->company]);

        $rejectedRequest = DB::transaction(function () use ($request, $actor): ?KioskTerminalAccessRequest {
            $request = KioskTerminalAccessRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->status === 'pending' && ! $request->expires_at->isFuture()) {
                $request->forceFill(['status' => 'expired'])->save();

                return null;
            }

            if ($request->status !== 'pending') {
                throw new InvalidArgumentException('La solicitud ya no esta disponible para rechazo.');
            }

            $request->forceFill([
                'status' => 'rejected',
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            return $request->refresh();
        });

        if (! $rejectedRequest) {
            throw new InvalidArgumentException('La solicitud ya no esta disponible para rechazo.');
        }

        return $rejectedRequest;
    }
}
