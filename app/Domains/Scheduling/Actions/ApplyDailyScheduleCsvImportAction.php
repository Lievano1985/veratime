<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\DailyScheduleCsvApplyResult;
use App\Domains\Scheduling\Exceptions\DailyScheduleCsvImportStateException;
use App\Domains\Scheduling\Exceptions\DailyScheduleCsvStalePreviewException;
use App\Models\EmploymentRelationship;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ApplyDailyScheduleCsvImportAction
{
    public function __construct(
        private ReplaceDraftDailyScheduleAssignmentAction $replaceAssignment,
        private BuildDailyScheduleCsvPreviewFingerprintAction $fingerprints,
    ) {
    }

    public function handle(User $actor, ImportBatch $importBatch): DailyScheduleCsvApplyResult
    {
        $importBatch->loadMissing('scheduleBatch.company');
        Gate::forUser($actor)->authorize('update', $importBatch->scheduleBatch);

        return DB::transaction(function () use ($actor, $importBatch): DailyScheduleCsvApplyResult {
            $lockedImport = ImportBatch::query()->with('rows')->lockForUpdate()->findOrFail($importBatch->id);
            $batch = $lockedImport->scheduleBatch()->with(['company', 'center'])->lockForUpdate()->firstOrFail();
            $company = $batch->company;

            if ($lockedImport->status !== 'validated' || $lockedImport->invalid_rows > 0) {
                throw new DailyScheduleCsvImportStateException('Solo una importacion validada y sin errores puede aplicarse.');
            }

            if ($batch->status !== 'draft') {
                throw new DailyScheduleCsvImportStateException('La aplicacion requiere un lote borrador.');
            }

            if ($this->fingerprints->handle($lockedImport) !== $lockedImport->validation_sha256) {
                throw new DailyScheduleCsvStalePreviewException('La programacion cambio despues de validar el archivo. Vuelve a validar antes de aplicar.');
            }

            $lockedImport->forceFill(['status' => 'applying'])->save();
            $applied = 0;
            $skipped = 0;

            $rows = $lockedImport->rows()->whereIn('status', ['valid', 'warning'])->orderBy('row_number')->lockForUpdate()->get();
            foreach ($rows as $row) {
                $normalized = $row->normalized_data ?? [];

                if ($row->status === 'warning') {
                    $row->forceFill(['status' => 'skipped'])->save();
                    $skipped++;
                    continue;
                }

                $relationship = EmploymentRelationship::query()->findOrFail($normalized['employment_relationship_id']);
                $assignment = $this->replaceAssignment->handle(
                    $company,
                    $batch,
                    $relationship,
                    $normalized['assignment'],
                    $normalized['segments'] ?? [],
                );

                $row->appliedDailyScheduleAssignment()->associate($assignment);
                $row->forceFill(['status' => 'applied'])->save();
                $applied++;
            }

            $creationSource = $this->resolveCreationSource($batch->fresh());
            $batch->forceFill(['creation_source' => $creationSource])->save();

            $lockedImport->forceFill([
                'status' => 'applied',
                'applied_rows' => $applied,
                'skipped_rows' => $skipped,
                'applied_by' => $actor->id,
                'applied_at' => now(),
            ])->save();

            return new DailyScheduleCsvApplyResult($lockedImport->refresh()->load('rows'), $applied, $skipped);
        });
    }

    private function resolveCreationSource(\App\Models\ScheduleBatch $batch): string
    {
        $sources = $batch->dailyAssignments()->select('source_type')->distinct()->pluck('source_type')->all();

        return $sources === ['csv'] ? 'csv' : 'mixed';
    }
}
