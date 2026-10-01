<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\KioskDevice;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CreateKioskDevicePairingAction
{
    /**
     * @return array{device: KioskDevice, pairing_code: string}
     */
    public function handle(Company $company, User $actor, string $name, ?int $centerId = null): array
    {
        Gate::forUser($actor)->authorize('create', [KioskDevice::class, $company]);

        $center = $this->resolveCenter($company, $centerId);
        $pairingCode = 'VTK-'.Str::upper(Str::random(48));

        $device = KioskDevice::query()->create([
            'company_id' => $company->id,
            'center_id' => $center?->id,
            'name' => trim($name),
            'status' => 'pending',
            'pairing_code_hash' => hash('sha256', $pairingCode),
            'pairing_expires_at' => now()->addHour(),
            'created_by_user_id' => $actor->id,
        ]);

        return [
            'device' => $device,
            'pairing_code' => $pairingCode,
        ];
    }

    private function resolveCenter(Company $company, ?int $centerId): ?Center
    {
        if (! $centerId) {
            return null;
        }

        $center = Center::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->find($centerId);

        if (! $center) {
            throw new InvalidArgumentException('El centro seleccionado no esta disponible para esta empresa.');
        }

        return $center;
    }
}
