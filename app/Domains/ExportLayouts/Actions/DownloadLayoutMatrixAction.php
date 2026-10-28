<?php

namespace App\Domains\ExportLayouts\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadLayoutMatrixAction
{
    public function handle(array $definition, array $matrix, string $filename): StreamedResponse
    {
        if ($matrix['errors'] !== [] || $matrix['worker_count'] === 0 || $matrix['incident_cells'] === 0) {
            throw ValidationException::withMessages(['export' => $matrix['errors'] ?: ['No hay incidencias para exportar con esta selección.']]);
        }
        $format = $definition['format'];

        return response()->streamDownload(function () use ($definition, $matrix, $format): void {
            $values = array_merge([$matrix['headers']], $matrix['rows']);
            if ($format === 'xlsx') {
                $book = new Spreadsheet;
                $sheet = $book->getActiveSheet();
                $sheet->setTitle('Incidencias');
                foreach ($values as $row => $cells) {
                    foreach ($cells as $column => $value) {
                        $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($column + 1).($row + 1), (string) $value, DataType::TYPE_STRING);
                    }
                }
                foreach ($matrix['date_cells'] as $dateCell) {
                    $cell = Coordinate::stringFromColumnIndex($dateCell['column'] + 1).($dateCell['row'] + 1);
                    $sheet->setCellValueExplicit($cell, Date::PHPToExcel(CarbonImmutable::parse($dateCell['date'])), DataType::TYPE_NUMERIC);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(match ($definition['date_format']) {
                        'Y-m-d' => 'yyyy-mm-dd', 'd/m' => 'dd/mm', default => 'dd/mm/yyyy'
                    });
                }
                $sheet->freezePane('B2');
                $sheet->getStyle('A1:'.Coordinate::stringFromColumnIndex(count($matrix['headers'])).'1')->getFont()->setBold(true);
                $sheet->getColumnDimension('A')->setWidth(24);
                for ($column = 2; $column <= count($matrix['headers']); $column++) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(17);
                }
                (new Xlsx($book))->save('php://output');
                $book->disconnectWorksheets();

                return;
            }
            $output = fopen('php://output', 'wb');
            if ($format === 'csv') {
                echo "\xEF\xBB\xBF";
            }
            foreach ($values as $row) {
                $safe = array_map(fn ($value) => preg_match('/^[\s]*[=+@\-]/u', (string) $value) ? "'".$value : (string) $value, $row);
                fputcsv($output, $safe, $definition['delimiter'] === 'tab' ? "\t" : $definition['delimiter'], '"', '', "\r\n");
            }
            fclose($output);
        }, $filename.'.'.$format, ['Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : ($format === 'csv' ? 'text/csv; charset=UTF-8' : 'text/plain; charset=UTF-8'), 'Cache-Control' => 'no-store']);
    }
}
