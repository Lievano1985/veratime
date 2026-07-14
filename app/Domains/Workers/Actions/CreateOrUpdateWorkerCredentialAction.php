<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\Worker;
use App\Models\WorkerCredential;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class CreateOrUpdateWorkerCredentialAction
{
    public function handle(Company $company, Worker $worker, array $data): WorkerCredential
    {
        if ($worker->company_id !== $company->id) {
            throw new InvalidArgumentException('La credencial de la persona trabajadora debe pertenecer a la empresa activa.');
        }

        $credential = $worker->credential()->first();

        if (! $credential && empty($data['temporal_pin'])) {
            throw new InvalidArgumentException('Se requiere un NIP temporal para crear la credencial.');
        }

        $credential = $company->workerCredentials()->updateOrCreate(
            ['worker_id' => $worker->id],
            [
                'access_code' => $data['access_code'] ?? $worker->employee_code,
                'status' => $data['status'] ?? 'active',
            ],
        );

        if (! empty($data['temporal_pin'])) {
            $credential->forceFill([
                'pin_hash' => Hash::make($data['temporal_pin']),
                'last_changed_at' => now(),
            ])->save();
        }

        return $credential->refresh();
    }
}
