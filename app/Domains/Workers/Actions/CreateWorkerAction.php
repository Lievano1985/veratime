<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\Worker;

class CreateWorkerAction
{
    public function handle(Company $company, array $data): Worker
    {
        return $company->workers()->create([
            'employee_code' => $data['employee_code'],
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'curp' => $data['curp'] ?? null,
            'rfc' => $data['rfc'] ?? null,
            'status' => $data['status'] ?? 'active',
            'source' => $data['source'] ?? 'web',
            'external_id' => $data['external_id'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }
}
