<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Center;
use App\Models\KioskDevice;
use App\Models\KioskTerminalAccessRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ApproveKioskTerminalAccessRequestAction
{
    public function handle(KioskTerminalAccessRequest $request, User $actor, ?int $centerId = null): KioskTerminalAccessRequest
    {
        Gate::forUser($actor)->authorize('create', [KioskDevice::class, $request->company]);

        $approvedRequest = DB::transaction(function () use ($request, $actor, $centerId): ?KioskTerminalAccessRequest {
            $request = KioskTerminalAccessRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->status === 'pending' && ! $request->expires_at->isFuture()) {
                $request->forceFill(['status' => 'expired'])->save();

                return null;
            }

            if ($request->status !== 'pending') {
                throw new InvalidArgumentException('La solicitud ya no esta disponible para autorizacion.');
            }

            $center = $this->resolveCenter($request, $centerId);

            $request->forceFill([
                'status' => 'approved',
                'center_id' => $center?->id,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            return $request->refresh();
        });

        if (! $approvedRequest) {
            throw new InvalidArgumentException('La solicitud ya no esta disponible para autorizacion.');
        }

        return $approvedRequest;
    }

    private function resolveCenter(KioskTerminalAccessRequest $request, ?int $centerId): ?Center
    {
        if (! $centerId) {
            return null;
        }

        $center = Center::query()
            ->where('company_id', $request->company_id)
            ->where('status', 'active')
            ->find($centerId);

        if (! $center) {
            throw new InvalidArgumentException('El centro seleccionado no esta disponible para esta empresa.');
        }

        return $center;
    }
}
