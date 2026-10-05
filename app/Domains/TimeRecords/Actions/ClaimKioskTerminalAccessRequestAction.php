<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\KioskDevice;
use App\Models\KioskTerminalAccessRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClaimKioskTerminalAccessRequestAction
{
    /** @return array{status: string, device?: KioskDevice, device_token?: string} */
    public function handle(string $requestSecret, ?string $ipAddress = null, ?string $userAgent = null): array
    {
        return DB::transaction(function () use ($requestSecret, $ipAddress, $userAgent): array {
            $request = KioskTerminalAccessRequest::query()
                ->lockForUpdate()
                ->where('request_secret_hash', hash('sha256', $requestSecret))
                ->first();

            if (! $request) {
                return ['status' => 'missing'];
            }

            if (in_array($request->status, ['pending', 'approved'], true) && ! $request->expires_at->isFuture()) {
                $request->forceFill(['status' => 'expired'])->save();
            }

            if ($request->status !== 'approved') {
                return ['status' => $request->status];
            }

            $deviceToken = 'vtd_'.Str::random(64);
            $device = KioskDevice::query()->create([
                'company_id' => $request->company_id,
                'center_id' => $request->center_id,
                'name' => $request->requested_name,
                'status' => 'active',
                'paired_at' => now(),
                'device_token_hash' => hash('sha256', $deviceToken),
                'created_by_user_id' => $request->reviewed_by_user_id,
                'last_seen_at' => now(),
                'last_seen_ip' => $ipAddress,
                'last_seen_user_agent' => Str::limit((string) $userAgent, 1000, ''),
            ]);

            $request->forceFill([
                'status' => 'claimed',
                'claimed_at' => now(),
                'kiosk_device_id' => $device->id,
            ])->save();

            return [
                'status' => 'claimed',
                'device' => $device,
                'device_token' => $deviceToken,
            ];
        });
    }
}
