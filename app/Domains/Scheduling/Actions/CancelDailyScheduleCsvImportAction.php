<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Exceptions\DailyScheduleCsvImportStateException;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CancelDailyScheduleCsvImportAction
{
    public function handle(User $actor, ImportBatch $importBatch, string $reason): ImportBatch
    {
        $importBatch->loadMissing('scheduleBatch');
        Gate::forUser($actor)->authorize('update', $importBatch->scheduleBatch);

        return DB::transaction(function () use ($actor, $importBatch, $reason): ImportBatch {
            $lockedImport = ImportBatch::query()->lockForUpdate()->findOrFail($importBatch->id);

            if (! in_array($lockedImport->status, ['uploaded', 'validated', 'invalid'], true)) {
                throw new DailyScheduleCsvImportStateException('La importacion ya no puede cancelarse.');
            }

            $lockedImport->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => trim($reason),
            ])->save();

            return $lockedImport->refresh();
        });
    }
}
