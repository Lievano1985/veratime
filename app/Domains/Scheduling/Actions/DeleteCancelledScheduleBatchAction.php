<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class DeleteCancelledScheduleBatchAction
{
    public function handle(User $actor, Company $company, ScheduleBatch $batch): void
    {
        $storedFiles = [];

        DB::transaction(function () use ($actor, $company, $batch, &$storedFiles): void {
            $batch = ScheduleBatch::query()
                ->with(['dailyAssignments.segments', 'imports.rows'])
                ->lockForUpdate()
                ->findOrFail($batch->id);

            Gate::forUser($actor)->authorize('deleteCancelled', $batch);

            if ($company->status !== 'active' || $batch->company_id !== $company->id) {
                throw new InvalidArgumentException('El lote no pertenece a la empresa activa.');
            }

            if ($batch->status !== 'cancelled') {
                throw new InvalidArgumentException('Solo se pueden eliminar definitivamente lotes cancelados.');
            }

            if ($batch->correctiveBatches()->exists()) {
                throw new InvalidArgumentException('El lote tiene versiones relacionadas y no puede eliminarse definitivamente.');
            }

            foreach ($batch->imports as $import) {
                if ($import->storage_disk && $import->storage_path) {
                    $storedFiles[] = [$import->storage_disk, $import->storage_path];
                }

                $import->rows()->delete();
                $import->delete();
            }

            foreach ($batch->dailyAssignments as $assignment) {
                $assignment->segments()->delete();
                $assignment->delete();
            }

            $batch->delete();
        });

        foreach ($storedFiles as [$disk, $path]) {
            Storage::disk($disk)->delete($path);
        }
    }
}
