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

            $previousBatch = isset($data['previous_batch_id'])
                ? ScheduleBatch::query()->lockForUpdate()->find($data['previous_batch_id'])
                : null;

            $version = isset($data['version'])
                ? max(1, (int) $data['version'])
                : ((int) ScheduleBatch::query()
                    ->where('company_id', $company->id)
                    ->where('center_id', $center->id)
                    ->whereDate('period_start', $periodStart)
                    ->whereDate('period_end', $periodEnd)
                    ->lockForUpdate()
                    ->max('version')) + 1;

            $this->validator->validatePreviousBatch($company, $center, $periodStart, $periodEnd, $previousBatch, $version);

            $batch = new ScheduleBatch([
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'version' => $version,
                'status' => 'draft',
                'creation_source' => $this->validator->validateCreationSource((string) ($data['creation_source'] ?? 'manual')),
                'notes' => blank($data['notes'] ?? null) ? null : trim((string) $data['notes']),
            ]);
            $batch->company()->associate($company);
            $batch->center()->associate($center);
            $batch->previousBatch()->associate($previousBatch);
            $batch->creator()->associate($createdBy);
            $batch->save();

            return $batch->refresh();
        });
    }
}
