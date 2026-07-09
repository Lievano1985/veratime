<?php

namespace App\Domains\Companies\Actions;

use App\Models\Center;
use App\Models\Company;

class CreateCenterAction
{
    public function handle(Company $company, array $data): Center
    {
        return $company->centers()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'status' => $data['status'] ?? 'active',
            'address' => $data['address'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }
}
