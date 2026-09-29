<?php

namespace App\Domains\Attendance\Actions;

use App\Models\AttendancePeriod;
use App\Models\PayrollExportTemplate;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportAttendancePeriodPayrollXlsxAction
{
    public function __construct(private readonly ExportAttendancePeriodPayrollCsvAction $exportData) {}

    public function handle(AttendancePeriod $period, ?PayrollExportTemplate $template = null): StreamedResponse
    {
        $columns = $this->exportData->columnsFor($template);
        $filename = $this->exportData->filename($period, 'xlsx');

        return response()->streamDownload(function () use ($period, $columns): void {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Asistencia');
            $lastColumn = Coordinate::stringFromColumnIndex(count($columns));

            foreach (array_values($columns) as $index => $column) {
                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($index + 1).'1',
                    $column['header'],
                    DataType::TYPE_STRING,
                );
            }

            $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F4E78');
            $sheet->freezePane('A2');

            $rowNumber = 2;
            $this->exportData->writeRows($period, $columns, function (array $row) use ($sheet, &$rowNumber): void {
                foreach (array_values($row) as $index => $value) {
                    $sheet->setCellValueExplicit(
                        Coordinate::stringFromColumnIndex($index + 1).$rowNumber,
                        (string) $value,
                        DataType::TYPE_STRING,
                    );
                }

                $rowNumber++;
            });

            $sheet->setAutoFilter('A1:'.$lastColumn.max(1, $rowNumber - 1));
            $spreadsheet->getProperties()->setCreator('Vera Time')->setTitle('Asistencia de período');

            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
