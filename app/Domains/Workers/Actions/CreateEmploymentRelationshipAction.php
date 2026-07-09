<?php

namespace App\Domains\Workers\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Worker;
use InvalidArgumentException;

class CreateEmploymentRelationshipAction
{
    public function handle(Company $company, Worker $worker, Center $center, array $data): EmploymentRelationship
    {
        if ($worker->company_id !== $company->id || $center->company_id !== $company->id) {
            throw new InvalidArgumentException('Worker and center must belong to the active company.');
        }

        return $company->employmentRelationships()->create([
            'worker_id' => $worker->id,
            'center_id' => $center->id,
            'position_name' => $data['position_name'] ?? null,
            'started_at' => $data['started_at'],
            'ended_at' => $data['ended_at'] ?? null,
            'status' => $data['status'] ?? 'active',
            'source' => $data['source'] ?? 'web',
            'external_id' => $data['external_id'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);
    }
}
