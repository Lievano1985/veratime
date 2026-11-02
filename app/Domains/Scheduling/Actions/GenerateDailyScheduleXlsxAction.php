<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Organization\Actions\ResolveEmploymentUnitsForDateAction;
use App\Domains\Scheduling\Support\ScheduleWorkbookFormat as Format;
use App\Models\Company;
use App\Models\ScheduleBatch;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Protection;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerateDailyScheduleXlsxAction
{
    public function handle(Company $company, ScheduleBatch $batch, array $filters = []): StreamedResponse
    {
        $book = $this->build($company, $batch, $filters);

        return response()->streamDownload(function () use ($book) {
            try {
                (new Xlsx($book))->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'vera-time-horarios-'.$batch->period_start->format('o-\WW').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function build(Company $company, ScheduleBatch $batch, array $filters = []): Spreadsheet
    {
        if ($company->status !== 'active' || $batch->company_id !== $company->id || $batch->status !== 'draft') {
            throw new InvalidArgumentException('Descarga Excel desde un borrador de tu empresa activa.');
        }
        $batch->loadMissing('center', 'dailyAssignments.shiftTemplate.segments', 'dailyAssignments.segments', 'dailyAssignments.employmentRelationship.worker');
        $days = $batch->dailyAssignments->keyBy(fn ($day) => $day->employment_relationship_id.'|'.$day->work_date->toDateString());
        $people = app(ResolveScheduleBatchExpectedRelationshipDatesAction::class)->handle($company, $batch);
        // A corrective draft must also retain historical relationships from its source.
        foreach ($batch->dailyAssignments->groupBy('employment_relationship_id') as $id => $historicalDays) {
            if (! $people->contains(fn ($item) => $item['relationship']->id === $id)) {
                $people->push(['relationship' => $historicalDays->first()->employmentRelationship, 'dates' => $historicalDays->map(fn ($day) => $day->work_date->toDateString())->values()->all()]);
            }
        }
        $search = mb_strtolower(trim($filters['worker_search'] ?? ''));
        $unitId = $filters['organizational_unit_id'] ?? null;
        $people = $people->filter(function ($item) use ($search, $unitId, $company) {
            $person = $item['relationship'];
            if (! $person || ($search !== '' && ! str_contains(mb_strtolower($person->worker->employee_code.' '.$person->worker->full_name), $search))) {
                return false;
            }

            return ! $unitId || app(ResolveEmploymentUnitsForDateAction::class)->handle($company, $person, $item['dates'][0])['primary']?->id === (int) $unitId;
        })->sortBy(fn ($item) => $item['relationship']->worker->employee_code)->values();

        $book = new Spreadsheet;
        $book->getProperties()->setCreator('Vera Time')->setTitle('Programación semanal de horarios');
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet = $book->getActiveSheet()->setTitle('Programación');
        $turnSheet = $book->createSheet()->setTitle('Turnos');
        $turns = $company->shiftTemplates()->with('segments')->where('status', 'active')->orderBy('name')->get();
        $this->heading($sheet, 'Programación de horarios', 'A', 'I');
        $sheet->mergeCells('A2:I2')->setCellValue('A2', $company->name.' · '.$batch->center->name.' · Semana '.$batch->period_start->isoWeek().' · '.$batch->period_start->format('d/m/Y').' al '.$batch->period_end->format('d/m/Y'));
        $sheet->mergeCells('A3:I3')->setCellValue('A3', 'Edita los días con las listas desplegables. Vacío o “Conservar horario actual” no cambia ese día. Guarda este mismo archivo .xlsx y súbelo para revisar los cambios.');
        $sheet->getStyle('A3:I3')->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(3)->setRowHeight(34);
        $sheet->getColumnDimension('A')->setWidth(19);
        $sheet->getColumnDimension('B')->setWidth(32);
        $headers = ['Código', 'Trabajador'];
        $dates = [];
        for ($i = 0; $i < 7; $i++) {
            $date = CarbonImmutable::parse($batch->period_start)->addDays($i);
            $dates[] = $date->toDateString();
            $headers[] = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'][$i]."\n".$date->format('d/m/Y');
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 3))->setWidth(26);
        }
        $this->writeRow($sheet, Format::HEADER_ROW, $headers);
        $this->headerStyle($sheet, 'A5:I5');
        $sheet->getRowDimension(5)->setRowHeight(34);
        $sheet->freezePane('C6');
        $sheet->setShowGridlines(false);

        $this->heading($turnSheet, 'Turnos disponibles', 'A', 'E');
        $turnSheet->mergeCells('A3:E3')->setCellValue('A3', 'Consulta aquí los turnos. En Programación elige una opción de la lista de cada día.');
        $this->writeRow($turnSheet, 5, ['Opción en la lista', 'Código', 'Nombre', 'Horario', 'Descripción']);
        $this->headerStyle($turnSheet, 'A5:E5');
        $this->writeRow($turnSheet, 6, ['DESCANSO', 'DESCANSO', 'Día de descanso', '', 'Asigna descanso al día seleccionado.']);
        $this->writeRow($turnSheet, 7, [Format::KEEP, '', 'Conservar', '', 'No cambia la programación de esa fecha.']);
        $optionRow = 8;
        foreach ($turns as $turn) {
            $this->writeRow($turnSheet, $optionRow++, [Format::label($turn), $turn->code, $turn->name, Format::range($turn), $turn->description ?? '']);
        }
        $preserveOptions = [];
        $baseline = [];
        foreach ($people as $index => $item) {
            $person = $item['relationship'];
            $row = Format::FIRST_ROW + $index;
            $this->writeRow($sheet, $row, [$person->worker->employee_code, $person->worker->full_name]);
            $sheet->getRowDimension($row)->setRowHeight(48);
            foreach ($dates as $offset => $date) {
                $cell = Coordinate::stringFromColumnIndex($offset + 3).$row;
                $day = $days->get($person->id.'|'.$date);
                $valid = in_array($date, $item['dates'], true);
                $value = '';
                if (! $valid) {
                    $value = '-';
                } elseif ($day?->day_type === 'rest') {
                    $value = 'DESCANSO';
                } elseif ($day?->day_type === 'shift' && $day->shiftTemplate?->status === 'active') {
                    $work = $day->segments->where('segment_type', 'work')->sortBy('segment_order');
                    $first = $work->first();
                    $last = $work->last();
                    $range = $first && $last ? substr($first->start_local_time, 0, 5).'–'.substr($last->end_local_time, 0, 5).($last->end_day_offset ? ' (+1 día)' : '') : null;
                    $value = Format::label($day->shiftTemplate, $range);
                } elseif ($day && $day->day_type !== 'unassigned') {
                    $value = '[ACTUAL] '.match ($day->day_type) {
                        'flexible' => 'Flexible', 'on_call' => 'Guardia', default => $day->shiftTemplate?->code ?? 'Horario'
                    }.' · conservar';
                    $preserveOptions[$value] = true;
                }
                $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
                if ($valid && $day && $day->day_type !== 'unassigned') {
                    $baseline[] = [$person->worker->employee_code, $date, $value];
                }
                $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($valid ? 'FFEAF3FF' : 'FFF1F5F9');
                if ($valid) {
                    $sheet->getStyle($cell)->getProtection()->setLocked(Protection::PROTECTION_UNPROTECTED);
                    $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FF0067E4');
                    $validation = $sheet->getCell($cell)->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(true)->setShowDropDown(true)
                        ->setShowErrorMessage(true)->setErrorTitle('Elige un turno de la lista')->setError('Selecciona turno, DESCANSO o conservar el horario actual.')
                        ->setShowInputMessage(true)->setPromptTitle('Asignar horario')->setPrompt('Elige un turno o descanso. Vacío conserva el día actual.')->setFormula1('OpcionesHorario');
                }
            }
        }
        foreach (array_keys($preserveOptions) as $label) {
            $this->writeRow($turnSheet, $optionRow++, [$label, '', 'Horario actual', '', 'Se conserva; modifica este día en el sistema si requiere configuración avanzada.']);
        }
        $book->addNamedRange(new NamedRange('OpcionesHorario', $turnSheet, '$A$6:$A$'.($optionRow - 1)));
        $lastRow = max(6, 5 + $people->count());
        $sheet->setAutoFilter('A5:I'.$lastRow);
        $sheet->getStyle('A5:I'.$lastRow)->getAlignment()->setVertical('center')->setWrapText(true);
        $sheet->getStyle('A6:B'.$lastRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF8FAFC');
        $rest = new Conditional;
        $rest->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_EQUAL)->addCondition('"DESCANSO"');
        $rest->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9FBF2');
        $rest->getStyle()->getFont()->getColor()->setARGB('FF047857');
        $sheet->getStyle('C6:I'.$lastRow)->setConditionalStyles([$rest]);
        $sheet->getProtection()->setSheet(true)->setAutoFilter(false)->setSelectLockedCells(false)->setSelectUnlockedCells(true);
        foreach (['A' => 58, 'B' => 20, 'C' => 30, 'D' => 24, 'E' => 56] as $col => $width) {
            $turnSheet->getColumnDimension($col)->setWidth($width);
        }
        $turnSheet->freezePane('C6');
        $turnSheet->setAutoFilter('A5:E'.($optionRow - 1));
        $turnSheet->getStyle('A5:E'.($optionRow - 1))->getAlignment()->setWrapText(true);
        $turnSheet->setShowGridlines(false);
        $context = $book->createSheet()->setTitle('_VeraTime');
        foreach ([Format::VERSION, (string) $company->id, (string) $batch->center_id, (string) $batch->id, $batch->period_start->toDateString(), $batch->period_end->toDateString()] as $i => $value) {
            $context->setCellValueExplicit('A'.($i + 1), $value, DataType::TYPE_STRING);
        }
        foreach ($baseline as $i => $values) {
            $this->writeRow($context, 10 + $i, $values);
        }
        $context->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    private function writeRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $i => $value) {
            $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1).$row, (string) $value, DataType::TYPE_STRING);
        }
    }

    private function heading(Worksheet $sheet, string $title, string $first, string $last): void
    {
        $sheet->mergeCells($first.'1:'.$last.'1')->setCellValue($first.'1', $title);
        $sheet->getStyle($first.'1')->getFont()->setBold(true)->setSize(20)->getColor()->setARGB('FF022B55');
        $sheet->getRowDimension(1)->setRowHeight(32);
    }

    private function headerStyle(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0067E4');
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getAlignment()->setWrapText(true);
    }
}
