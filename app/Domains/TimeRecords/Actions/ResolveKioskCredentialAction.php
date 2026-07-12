<?php

namespace App\Domains\TimeRecords\Actions;

use App\Models\WorkerCredential;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class ResolveKioskCredentialAction
{
    public function handle(string $accessCode, string $pin): WorkerCredential
    {
        $accessCode = trim($accessCode);

        if ($accessCode === '' || trim($pin) === '') {
            throw new InvalidArgumentException('No se pudo validar la credencial.');
        }

        $credential = $this->findCredential($accessCode);

        if (! $credential) {
            throw new InvalidArgumentException('No se pudo validar la credencial.');
        }

        $this->assertCredentialCanUseKiosk($credential);

        if (! $credential->pin_hash || ! Hash::check($pin, $credential->pin_hash)) {
            $credential->forceFill([
                'failed_attempts' => $credential->failed_attempts + 1,
            ])->save();

            throw new InvalidArgumentException('No se pudo validar la credencial.');
        }

        $credential->forceFill([
            'failed_attempts' => 0,
            'last_used_at' => now(),
        ])->save();

        return $credential->refresh()->load(['company', 'worker']);
    }

    private function findCredential(string $accessCode): ?WorkerCredential
    {
        $credentials = WorkerCredential::query()
            ->with(['company', 'worker'])
            ->where(function ($query) use ($accessCode): void {
                $query->where('access_code', $accessCode)
                    ->orWhereHas('worker', fn ($workerQuery) => $workerQuery->where('employee_code', $accessCode));
            })
            ->get();

        if ($credentials->count() !== 1) {
            return null;
        }

        return $credentials->first();
    }

    private function assertCredentialCanUseKiosk(WorkerCredential $credential): void
    {
        if ($credential->status !== 'active') {
            throw new InvalidArgumentException('No se pudo validar la credencial.');
        }

        if (! $credential->company || $credential->company->status !== 'active') {
            throw new InvalidArgumentException('No se pudo validar la credencial.');
        }

        if (! $credential->worker || $credential->worker->status !== 'active') {
            throw new InvalidArgumentException('No se pudo validar la credencial.');
        }

        if ($credential->worker->company_id !== $credential->company_id) {
            throw new InvalidArgumentException('No se pudo validar la credencial.');
        }
    }
}