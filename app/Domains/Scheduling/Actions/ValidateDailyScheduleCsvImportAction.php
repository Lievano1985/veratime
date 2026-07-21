<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\DailyScheduleCsvValidationResult;
use App\Domains\Scheduling\Exceptions\DailyScheduleCsvImportStateException;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ValidateDailyScheduleCsvImportAction
{
    public function __construct(
        private ParseDailyScheduleCsvAction $parser,
        private ResolveDailyScheduleCsvRowAction $rowResolver,
        private BuildDailyScheduleCsvPreviewFingerprintAction $fingerprints,
    ) {
    }

    public function handle(User $actor, ImportBatch $importBatch): DailyScheduleCsvValidationResult
    {
        $importBatch->loadMissing('scheduleBatch.company', 'scheduleBatch.center');
        Gate::forUser($actor)->authorize('update', $importBatch->scheduleBatch);

        if (! in_array($importBatch->status, ['uploaded', 'invalid', 'validated'], true)) {
            throw new DailyScheduleCsvImportStateException('Solo una importacion cargada o validada puede validarse de nuevo.');
        }

        $this->assertFileStillMatches($importBatch);
        $parsed = $this->parser->handle((string) $importBatch->storage_disk, (string) $importBatch->storage_path);

        return DB::transaction(function () use ($actor, $importBatch, $parsed): DailyScheduleCsvValidationResult {
            $lockedImport = ImportBatch::query()->lockForUpdate()->findOrFail($importBatch->id);
            $batch = $lockedImport->scheduleBatch()->with(['company', 'center'])->lockForUpdate()->firstOrFail();

            if ($batch->status !== 'draft') {
                throw new DailyScheduleCsvImportStateException('La validacion requiere un lote borrador.');
            }

            $lockedImport->rows()->delete();
            $lockedImport->forceFill([
                'status' => 'validating',
                'validated_by' => null,
                'validated_at' => null,
                'validation_sha256' => null,
                'total_rows' => 0,
                'valid_rows' => 0,
                'invalid_rows' => 0,
                'warning_rows' => 0,
                'applied_rows' => 0,
                'skipped_rows' => 0,
            ])->save();

            $seen = [];
            $total = $valid = $invalid = $warning = 0;

            foreach ($parsed['rows'] as $parsedRow) {
                $result = $this->rowResolver->handle($lockedImport, $batch, $parsedRow['row_number'], $parsedRow['data']);
                $normalized = $result->normalizedData;
                $errors = $result->errors;
                $warnings = $result->warnings;
                $status = $result->status;

                if ($normalized) {
                    $key = $normalized['employment_relationship_id'].'|'.$normalized['work_date'];
                    if (isset($seen[$key])) {
                        $status = 'invalid';
                        $errors[] = 'La fila duplica trabajador y fecha dentro del mismo archivo.';
                        $normalized = null;
                    } else {
                        $seen[$key] = true;
                    }
                }

                $row = new ImportRow([
                    'row_number' => $result->rowNumber,
                    'status' => $status,
                    'raw_data' => $result->rawData,
                    'normalized_data' => $normalized,
                    'errors' => $errors,
                    'warnings' => $warnings,
                    'work_date' => $normalized['work_date'] ?? null,
                    'row_fingerprint' => $normalized ? $this->fingerprints->rowFingerprint($normalized) : null,
                ]);
                $row->company()->associate($lockedImport->company_id);
                $row->importBatch()->associate($lockedImport);
                $row->employmentRelationship()->associate($result->relationship);
                $row->existingDailyScheduleAssignment()->associate($result->existingAssignment);
                $row->save();

                $total++;
                if ($status === 'invalid') {
                    $invalid++;
                } elseif ($status === 'warning') {
                    $warning++;
                } else {
                    $valid++;
                }
            }

            $lockedImport->forceFill([
                'status' => $invalid > 0 ? 'invalid' : 'validated',
                'total_rows' => $total,
                'valid_rows' => $valid,
                'invalid_rows' => $invalid,
                'warning_rows' => $warning,
                'validated_by' => $actor->id,
                'validated_at' => now(),
            ])->save();

            if ($invalid === 0) {
                $lockedImport->forceFill([
                    'validation_sha256' => $this->fingerprints->handle($lockedImport->refresh()),
                ])->save();
            }

            return new DailyScheduleCsvValidationResult($lockedImport->refresh()->load('rows'));
        });
    }

    private function assertFileStillMatches(ImportBatch $importBatch): void
    {
        $stream = Storage::disk((string) $importBatch->storage_disk)->readStream((string) $importBatch->storage_path);
        if ($stream === false) {
            throw new DailyScheduleCsvImportStateException('No fue posible leer el archivo CSV registrado.');
        }
        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        fclose($stream);

        if (hash_final($hash) !== $importBatch->file_sha256) {
            throw new DailyScheduleCsvImportStateException('El archivo CSV cambio despues del registro de importacion.');
        }
    }
}
