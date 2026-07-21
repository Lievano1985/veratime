<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ScheduleBatch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListDailyScheduleCsvImportsAction
{
    public function handle(Company $company, ScheduleBatch $targetBatch, int $perPage = 5, string $pageName = 'csvImportsPage'): LengthAwarePaginator
    {
        abort_unless($company->status === 'active' && $targetBatch->company_id === $company->id, 403);

        return ImportBatch::query()
            ->where('company_id', $company->id)
            ->where('import_type', 'daily_schedule')
            ->where('target_type', 'schedule_batch')
            ->where('target_id', $targetBatch->id)
            ->with(['creator', 'validator', 'applier', 'canceller'])
            ->latest()
            ->paginate($perPage, ['*'], $pageName);
    }
}
