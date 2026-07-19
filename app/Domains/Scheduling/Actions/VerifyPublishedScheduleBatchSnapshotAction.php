<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\VerifyScheduleBatchSnapshotResult;
use App\Models\Company;
use App\Models\ScheduleBatch;

class VerifyPublishedScheduleBatchSnapshotAction
{
    public function handle(Company $company, ScheduleBatch $batch): VerifyScheduleBatchSnapshotResult
    {
        $errors = [];
        $actualHash = $batch->snapshot_sha256;
        $expectedHash = null;
        $jsonValid = false;

        if ($batch->company_id !== $company->id) {
            $errors[] = 'El lote no pertenece a la empresa indicada.';
        }

        if (! in_array($batch->status, ['published', 'superseded'], true)) {
            $errors[] = 'Solo se verifica un lote publicado o sustituido.';
        }

        if ($batch->snapshot_schema_version !== BuildScheduleBatchSnapshotAction::SCHEMA_VERSION) {
            $errors[] = 'La version del snapshot no es compatible.';
        }

        if (blank($batch->snapshot_canonical_json)) {
            $errors[] = 'El snapshot canonico no esta disponible.';
        } else {
            json_decode((string) $batch->snapshot_canonical_json, true);
            $jsonValid = json_last_error() === JSON_ERROR_NONE;
            if (! $jsonValid) {
                $errors[] = 'El snapshot canonico no es JSON valido.';
            }

            $expectedHash = hash('sha256', (string) $batch->snapshot_canonical_json);
        }

        if (! is_string($actualHash) || ! preg_match('/^[a-f0-9]{64}$/', $actualHash)) {
            $errors[] = 'El hash SHA-256 persistido no es valido.';
        } elseif ($expectedHash && ! hash_equals($expectedHash, $actualHash)) {
            $errors[] = 'El hash SHA-256 no coincide con el snapshot persistido.';
        }

        return new VerifyScheduleBatchSnapshotResult(
            valid: $errors === [],
            expectedHash: $expectedHash,
            actualHash: $actualHash,
            schemaVersion: $batch->snapshot_schema_version,
            jsonValid: $jsonValid,
            errors: $errors,
        );
    }
}
