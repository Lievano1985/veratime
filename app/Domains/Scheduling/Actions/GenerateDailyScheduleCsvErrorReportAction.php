<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerateDailyScheduleCsvErrorReportAction
{
    public function handle(User $actor, Company $company, ImportBatch $importBatch): StreamedResponse
    {
        $importBatch->loadMissing('scheduleBatch');
        Gate::forUser($actor)->authorize('update', $importBatch->scheduleBatch);

        abort_unless($company->status === 'active' && $importBatch->company_id === $company->id, 403);
        abort_unless($importBatch->import_type === 'daily_schedule' && $importBatch->target_type === 'schedule_batch', 404);

        $filename = sprintf('vera-time-importacion-%d-errores.csv', $importBatch->id);

        return response()->streamDownload(function () use ($importBatch): void {
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['fila', 'clave_empleado', 'fecha', 'tipo_dia', 'estado', 'errores', 'advertencias']);

            $importBatch->rows()
                ->where(function ($query): void {
                    $query->where('status', 'invalid')
                        ->orWhereJsonLength('warnings', '>', 0);
                })
                ->orderBy('row_number')
                ->chunk(200, function ($rows) use ($output): void {
                    foreach ($rows as $row) {
                        $raw = $row->raw_data ?? [];
                        fputcsv($output, [
                            $row->row_number,
                            $this->safeCsvValue((string) ($raw['clave_empleado'] ?? '')),
                            $this->safeCsvValue((string) ($raw['fecha'] ?? '')),
                            $this->safeCsvValue((string) ($raw['tipo_dia'] ?? '')),
                            $this->statusLabel((string) $row->status),
                            $this->safeCsvValue(implode(' | ', $row->errors ?? [])),
                            $this->safeCsvValue(implode(' | ', $row->warnings ?? [])),
                        ]);
                    }
                });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function safeCsvValue(string $value): string
    {
        $trimmed = ltrim($value);

        return $trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)
            ? "'".$value
            : $value;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'invalid' => 'Con errores',
            'warning' => 'Con advertencias',
            'valid' => 'Valida',
            'applied' => 'Aplicada',
            'skipped' => 'Omitida',
            default => 'Registrada',
        };
    }
}
