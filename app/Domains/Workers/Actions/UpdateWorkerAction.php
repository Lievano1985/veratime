<?php

namespace App\Domains\Workers\Actions;

use App\Models\Worker;

class UpdateWorkerAction
{
    public function handle(Worker $worker, array $data): Worker
    {
        $worker->fill([
            'employee_code' => $data['employee_code'],
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'curp' => $data['curp'] ?? null,
            'rfc' => $data['rfc'] ?? null,
            'status' => $data['status'],
            'metadata' => $data['metadata'] ?? $worker->metadata ?? [],
        ]);

        $worker->save();

        return $worker->refresh();
    }
}
