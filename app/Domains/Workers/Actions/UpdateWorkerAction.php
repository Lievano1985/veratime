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
            'birth_date' => array_key_exists('birth_date', $data)
                ? (filled($data['birth_date']) ? $data['birth_date'] : null)
                : $worker->birth_date,
            'email' => array_key_exists('email', $data) ? $data['email'] : $worker->email,
            'phone' => array_key_exists('phone', $data) ? $data['phone'] : $worker->phone,
            'curp' => array_key_exists('curp', $data) ? $data['curp'] : $worker->curp,
            'rfc' => $data['rfc'] ?? null,
            'status' => $data['status'],
            'metadata' => $data['metadata'] ?? $worker->metadata ?? [],
        ]);

        $worker->save();

        return $worker->refresh();
    }
}
