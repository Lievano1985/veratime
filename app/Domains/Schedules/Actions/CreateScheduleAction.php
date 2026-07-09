<?php

namespace App\Domains\Schedules\Actions;

use App\Models\Company;
use App\Models\Schedule;

class CreateScheduleAction
{
    public function handle(Company $company, array $data): Schedule
    {
        return $company->schedules()->create([
            'code' => $data['code'],
            'name' => $data['name'],
            'legal_type' => $data['legal_type'],
            'timezone' => $data['timezone'] ?? null,
            'status' => $data['status'] ?? 'active',
            'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }
}
