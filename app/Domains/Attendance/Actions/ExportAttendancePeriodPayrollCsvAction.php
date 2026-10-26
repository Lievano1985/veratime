<?php

namespace App\Domains\Attendance\Actions;

use App\Models\AttendancePeriod;
use App\Models\PayrollExportTemplate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportAttendancePeriodPayrollCsvAction
{
    public function __construct(private readonly ResolveAttendancePeriodPayrollRowsAction $historicalRows) {}

    public function handle(AttendancePeriod $period, ?PayrollExportTemplate $template = null): StreamedResponse
    {
        $columns = $this->columnsFor($template);
        $delimiter = $template?->delimiter ?: ',';
        $filename = $this->filename($period, 'csv');
        $rows = $this->historicalRows->handle($period);

        return response()->streamDownload(function () use ($rows, $columns, $delimiter): void {
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'wb');
            fputcsv($output, $this->sanitizeRow(array_column($columns, 'header')), $delimiter);

            $this->writeCapturedRows($rows, $columns, function (array $row) use ($output, $delimiter): void {
                fputcsv($output, $this->sanitizeRow($row), $delimiter);
            });

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @return list<array{key: string, header: string}>
     */
    public function columnsFor(?PayrollExportTemplate $template = null): array
    {
        $template?->loadMissing('columns');

        return $template instanceof PayrollExportTemplate
            ? $template->columns
                ->map(fn ($column): array => ['key' => $column->source_key, 'header' => $column->header])
                ->all()
            : self::defaultColumns();
    }

    public function filename(AttendancePeriod $period, string $extension): string
    {
        $period->loadMissing('center');

        return sprintf(
            'vera-time-asistencia-%s-%s-%s.%s',
            $this->slug((string) $period->center?->name),
            $period->period_start?->format('Ymd'),
            $period->period_end?->format('Ymd'),
            $extension,
        );
    }

    /**
     * @param  list<array{key: string, header: string}>  $columns
     * @param  callable(list<string|int>): void  $writeRow
     */
    public function writeRows(AttendancePeriod $period, array $columns, callable $writeRow): void
    {
        $this->writeCapturedRows($this->historicalRows->handle($period), $columns, $writeRow);
    }

    /**
     * @param  list<array<string, string|int>>  $rows
     * @param  list<array{key: string, header: string}>  $columns
     * @param  callable(list<string|int>): void  $writeRow
     */
    public function writeCapturedRows(array $rows, array $columns, callable $writeRow): void
    {
        foreach ($rows as $values) {
            $writeRow(array_map(fn (array $column): string|int => $values[$column['key']] ?? '', $columns));
        }
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return array_map(fn (string $header): array => ['key' => $header, 'header' => $header], [
            'empresa',
            'centro',
            'unidad',
            'empleado_id',
            'numero_empleado',
            'nombre_empleado',
            'rfc',
            'curp',
            'nss',
            'fecha',
            'tipo_dia',
            'estatus_jornada',
            'hora_entrada',
            'hora_salida',
            'horas_ordinarias',
            'horas_extra_totales',
            'horas_extra_dobles',
            'horas_extra_triples',
            'horas_nocturnas',
            'domingo_trabajado',
            'horas_domingo_trabajado',
            'prima_dominical_aplica',
            'descanso_programado',
            'descanso_trabajado',
            'descanso_domingo_trabajado',
            'descanso_semanal_trabajado',
            'descanso_obligatorio_trabajado',
            'horas_festivo_trabajado',
            'horas_festivo_pago_normal',
            'horas_festivo_pago_doble_adicional',
            'festivo_referencia',
            'horas_descanso_pagado',
            'horas_descanso_no_pagado',
            'minutos_retardo',
            'dictamen_retardo',
            'minutos_salida_anticipada',
            'dictamen_salida_anticipada',
            'minutos_ausencia',
            'tiene_incidencia',
            'tipo_incidencia',
            'estatus_incidencia',
            'incidencia_pagada',
            'incidencia_no_pagada',
            'observaciones',
        ]);
    }

    /**
     * @param  list<string|int>  $row
     * @return list<string|int>
     */
    private function sanitizeRow(array $row): array
    {
        return array_map(function (string|int $value): string|int {
            if (! is_string($value) || $value === '') {
                return $value;
            }

            return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
        }, $row);
    }

    private function slug(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower($value));

        return trim((string) $slug, '-') ?: 'periodo';
    }
}
