<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Exceptions\InvalidDailyScheduleCsvException;
use App\Domains\Scheduling\Support\DailyScheduleCsvSchema;
use App\Domains\Scheduling\Support\ScheduleWorkbookFormat as Format;
use App\Models\ScheduleBatch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;
use ZipArchive;

/** Converts workbook values into the same CSV stream consumed by the existing validator. */
class ReadDailyScheduleXlsxAction
{
    public function handle(string $disk, string $path, ?ScheduleBatch $batch)
    {
        if (! $batch) {
            throw new InvalidDailyScheduleCsvException('Selecciona el borrador destino antes de cargar Excel.');
        }
        $temp = tmpfile();
        $input = Storage::disk($disk)->readStream($path);
        if (! $temp || ! $input) {
            if (is_resource($temp)) {
                fclose($temp);
            }
            if (is_resource($input)) {
                fclose($input);
            }
            throw new InvalidDailyScheduleCsvException('No fue posible leer el archivo Excel.');
        }
        $book = null;
        $output = null;
        try {
            stream_copy_to_stream($input, $temp);
            $tempPath = stream_get_meta_data($temp)['uri'];
            $this->checkArchive($tempPath);
            $reader = new Xlsx;
            $reader->setReadDataOnly(true)->setReadEmptyCells(false)->setLoadSheetsOnly(['Programación', '_VeraTime']);
            $maxRows = (int) config('imports.daily_schedule_csv.max_rows', 10000);
            foreach ($reader->listWorksheetInfo($tempPath) as $info) {
                if ($info['worksheetName'] === 'Programación' && ($info['totalRows'] > $maxRows + 5 || $info['totalColumns'] > 9)) {
                    throw new InvalidDailyScheduleCsvException('El Excel supera el tamaño de la plantilla. Usa solo las columnas y filas de programación.');
                }
            }
            $book = $reader->load($tempPath);
            $sheet = $book->getSheetByName('Programación');
            $context = $book->getSheetByName('_VeraTime');
            if (! $sheet || ! $context) {
                throw new InvalidDailyScheduleCsvException('Usa la plantilla Excel descargada desde este borrador; no se encontraron sus hojas de programación.');
            }
            $expected = [Format::VERSION, (string) $batch->company_id, (string) $batch->center_id, (string) $batch->id, $batch->period_start->toDateString(), $batch->period_end->toDateString()];
            foreach ($expected as $i => $value) {
                if ($this->text($context, 'A'.($i + 1)) !== $value) {
                    throw new InvalidDailyScheduleCsvException('El Excel corresponde a otra empresa, centro o semana. Descarga la plantilla del borrador que deseas modificar.');
                }
            }
            $dates = [];
            for ($i = 0; $i < 7; $i++) {
                $date = CarbonImmutable::parse($batch->period_start)->addDays($i);
                $dates[] = $date->toDateString();
                $header = $this->text($sheet, chr(67 + $i).Format::HEADER_ROW);
                if (! str_ends_with($header, $date->format('d/m/Y'))) {
                    throw new InvalidDailyScheduleCsvException('Las fechas del Excel cambiaron. Conserva los encabezados de la plantilla descargada.');
                }
            }
            $output = fopen('php://temp', 'w+b');
            fputcsv($output, DailyScheduleCsvSchema::headers());
            $baseline = [];
            for ($row = 10; $row <= $context->getHighestDataRow(); $row++) {
                $baseline[$this->text($context, 'A'.$row).'|'.$this->text($context, 'B'.$row)] = $this->text($context, 'C'.$row);
            }
            for ($row = Format::FIRST_ROW; $row <= $sheet->getHighestDataRow(); $row++) {
                $values = [$this->text($sheet, 'A'.$row), $this->text($sheet, 'B'.$row)];
                for ($i = 0; $i < 7; $i++) {
                    $value = $this->text($sheet, chr(67 + $i).$row);
                    $original = $baseline[$values[0].'|'.$dates[$i]] ?? null;
                    if (($original !== null && $value === $original) || $value === Format::KEEP || str_starts_with($value, '[ACTUAL] ')) {
                        continue;
                    }
                    if ($value === '' || $value === '-') {
                        continue;
                    }
                    $isRest = mb_strtoupper($value) === 'DESCANSO';
                    $record = array_replace(array_fill_keys(DailyScheduleCsvSchema::headers(), ''), [
                        'clave_empleado' => $values[0], 'fecha' => $dates[$i],
                        'tipo_dia' => $isRest ? 'descanso' : 'turno',
                        'codigo_turno' => $isRest ? '' : explode(' · ', $value, 2)[0],
                        'motivo' => 'Carga por plantilla horizontal: '.$values[1].'.',
                    ]);
                    fputcsv($output, array_values($record));
                }
            }
            rewind($output);

            return $output;
        } catch (InvalidDailyScheduleCsvException $exception) {
            if (is_resource($output)) {
                fclose($output);
            }
            throw $exception;
        } catch (Throwable $exception) {
            if (is_resource($output)) {
                fclose($output);
            }
            throw new InvalidDailyScheduleCsvException('No se pudo leer el Excel. Descarga la plantilla y guarda los cambios como archivo .xlsx válido.', previous: $exception);
        } finally {
            $book?->disconnectWorksheets();
            fclose($input);
            fclose($temp);
        }
    }

    private function text(Worksheet $sheet, string $coordinate): string
    {
        $cell = $sheet->getCell($coordinate);
        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
            throw new InvalidDailyScheduleCsvException('La celda '.$sheet->getTitle().'!'.$coordinate.' contiene una fórmula. Selecciona un turno o escribe un valor, sin fórmulas.');
        }

        return trim((string) $cell->getValue());
    }

    private function checkArchive(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new InvalidDailyScheduleCsvException('El archivo no es un Excel .xlsx válido.');
        }
        try {
            $bytes = 0;
            if ($zip->numFiles > 2000) {
                throw new InvalidDailyScheduleCsvException('El Excel contiene demasiados elementos. Usa la plantilla de horarios.');
            }
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->statIndex($i);
                $bytes += $entry['size'];
                if ($bytes > 64 * 1024 * 1024 || str_contains(strtolower($entry['name']), 'vbaproject')) {
                    throw new InvalidDailyScheduleCsvException('El Excel es demasiado grande o contiene macros. Usa una plantilla .xlsx sin macros.');
                }
            }
        } finally {
            $zip->close();
        }
    }
}
