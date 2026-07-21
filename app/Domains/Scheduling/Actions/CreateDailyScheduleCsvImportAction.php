<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\CreateDailyScheduleCsvImportResult;
use App\Domains\Scheduling\Exceptions\DailyScheduleCsvImportStateException;
use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class CreateDailyScheduleCsvImportAction
{
    public function handle(User $actor, Company $company, ScheduleBatch $targetBatch, array $data): CreateDailyScheduleCsvImportResult
    {
        Gate::forUser($actor)->authorize('update', $targetBatch);
        $targetBatch->loadMissing('company', 'center');

        if ($company->status !== 'active' || $targetBatch->company_id !== $company->id || $targetBatch->status !== 'draft') {
            throw new DailyScheduleCsvImportStateException('La importacion requiere un lote borrador de la empresa activa.');
        }

        $disk = (string) ($data['storage_disk'] ?? 'local');
        $path = trim((string) ($data['storage_path'] ?? ''));
        $filename = trim((string) ($data['original_filename'] ?? basename($path)));
        $reason = trim(preg_replace('/\s+/', ' ', (string) ($data['reason'] ?? '')) ?? '');
        $policy = (string) ($data['existing_assignment_policy'] ?? 'replace_existing');
        $idempotencyKey = blank($data['idempotency_key'] ?? null) ? null : trim((string) $data['idempotency_key']);

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            throw new InvalidArgumentException('El archivo CSV privado no existe.');
        }

        if (! str_ends_with(mb_strtolower($filename), '.csv')) {
            throw new InvalidArgumentException('El archivo debe tener extension .csv.');
        }

        if ($reason === '') {
            throw new InvalidArgumentException('La importacion requiere un motivo.');
        }

        if (! in_array($policy, ['preserve_existing', 'replace_existing'], true)) {
            throw new InvalidArgumentException('La politica de registros existentes no es valida.');
        }

        $size = (int) Storage::disk($disk)->size($path);
        if ($size > (int) config('imports.daily_schedule_csv.max_file_size_bytes', 10485760)) {
            throw new InvalidArgumentException('El archivo CSV supera el tamano maximo permitido.');
        }

        if ($idempotencyKey) {
            $existing = ImportBatch::query()
                ->where('company_id', $company->id)
                ->where('import_type', 'daily_schedule')
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return new CreateDailyScheduleCsvImportResult($existing, true);
            }
        }

        $stream = Storage::disk($disk)->readStream($path);
        if ($stream === false) {
            throw new InvalidArgumentException('No fue posible calcular la huella del archivo.');
        }
        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        fclose($stream);

        $import = new ImportBatch([
            'import_type' => 'daily_schedule',
            'target_type' => 'schedule_batch',
            'target_id' => $targetBatch->id,
            'status' => 'uploaded',
            'existing_assignment_policy' => $policy,
            'original_filename' => $filename,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'file_sha256' => hash_final($hash),
            'file_size_bytes' => $size,
            'encoding' => 'UTF-8',
            'delimiter' => ',',
            'header_schema_version' => 1,
            'idempotency_key' => $idempotencyKey,
            'reason' => $reason,
            'metadata' => ['created_from' => 'domain_action'],
        ]);
        $import->company()->associate($company);
        $import->creator()->associate($actor);
        $import->save();

        return new CreateDailyScheduleCsvImportResult($import->refresh());
    }
}
