<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Exceptions\DailyScheduleCsvImportStateException;
use App\Models\Company;
use App\Models\ScheduleBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StoreDailyScheduleCsvUploadAction
{
    /**
     * @return array{disk: string, path: string, original_filename: string, size: int}
     */
    public function handle(Company $company, ScheduleBatch $targetBatch, UploadedFile $file): array
    {
        $targetBatch->loadMissing('center');

        if ($company->status !== 'active' || $targetBatch->company_id !== $company->id || $targetBatch->status !== 'draft') {
            throw new DailyScheduleCsvImportStateException('La carga requiere un lote borrador de la empresa activa.');
        }

        $extension = mb_strtolower((string) $file->getClientOriginalExtension());
        if ($extension !== 'csv') {
            throw new InvalidArgumentException('El archivo debe tener extension .csv.');
        }

        $maxSize = (int) config('imports.daily_schedule_csv.max_file_size_bytes', 10485760);
        if ($file->getSize() > $maxSize) {
            throw new InvalidArgumentException('El archivo CSV supera el tamano maximo permitido.');
        }

        $disk = 'local';
        $path = sprintf(
            'imports/%d/daily-schedules/%s/%s.csv',
            $company->id,
            (string) Str::uuid(),
            Str::random(40),
        );

        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw new InvalidArgumentException('No fue posible leer el archivo temporal.');
        }

        try {
            Storage::disk($disk)->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'size' => (int) $file->getSize(),
        ];
    }

    public function deleteStoredFile(string $disk, string $path): void
    {
        if ($path !== '' && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
