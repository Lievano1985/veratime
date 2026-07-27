<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateScheduleBatchAction
{
    public function __construct(private ValidateScheduleBatchAction $validator)
    {
    }

    public function handle(Company $company, Center $center, array $data = [], ?User $createdBy = null): ScheduleBatch
    {
        return DB::transaction(function () use ($company, $center, $data, $createdBy): ScheduleBatch {
            $this->validator->validateCenter($company, $center);
            [$periodStart, $periodEnd] = $this->validator->validatePeriod(
                (string) ($data['period_start'] ?? ''),
                (string) ($data['period_end'] ?? ''),
            );

            $this->validator->ensureNoOpenDraft($company, $center, $periodStart, $periodEnd);

            $batch = new ScheduleBatch([
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'version' => null,
                'status' => 'draft',
                'creation_source' => $this->validator->validateCreationSource((string) ($data['creation_source'] ?? 'manual')),
                'notes' => blank($data['notes'] ?? null) ? null : trim((string) $data['notes']),
            ]);
            $batch->company()->associate($company);
            $batch->center()->associate($center);
            $batch->creator()->associate($createdBy);
            $batch->save();

            return $batch->refresh();
        });
    }
}
