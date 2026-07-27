<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Exceptions\DailyScheduleCsvHeaderException;
use App\Domains\Scheduling\Exceptions\InvalidDailyScheduleCsvException;
use App\Domains\Scheduling\Support\DailyScheduleCsvSchema;
use Illuminate\Support\Facades\Storage;

class ParseDailyScheduleCsvAction
{
    /**
     * @return array{headers: list<string>, rows: list<array{row_number: int, data: array<string, string>}>}
     */
    public function handle(string $disk, string $path): array
    {
        $stream = Storage::disk($disk)->readStream($path);
        if ($stream === false) {
            throw new InvalidDailyScheduleCsvException('No fue posible leer el archivo CSV privado.');
        }

        try {
            $headerInfo = $this->readHeaders($stream);
            $headers = $headerInfo['headers'];
            $delimiter = $headerInfo['delimiter'];
            $isHorizontal = $this->isHorizontalTemplate($headers);
            $rows = [];
            $rowNumber = 1;
            $maxRows = (int) config('imports.daily_schedule_csv.max_rows', 10000);

            while (($values = fgetcsv($stream, 0, $delimiter)) !== false) {
                $rowNumber++;
                $values = array_map(fn ($value): string => $this->cleanValue((string) $value), $values);

                if ($this->isEmptyRow($values)) {
                    continue;
                }

                if (count($values) !== count($headers)) {
                    throw new InvalidDailyScheduleCsvException("La fila {$rowNumber} no coincide con el numero de columnas esperado.");
                }

                foreach ($values as $value) {
                    $this->assertSafeText($value, $rowNumber);
                }

                $row = array_combine($headers, $values);
                if ($isHorizontal) {
                    foreach ($this->expandHorizontalRow($rowNumber, $row, $headers) as $expandedRow) {
                        $rows[] = $expandedRow;
                    }
                } else {
                    $rows[] = [
                        'row_number' => $rowNumber,
                        'data' => $row,
                    ];
                }

                if (count($rows) > $maxRows) {
                    throw new InvalidDailyScheduleCsvException("El archivo supera el limite de {$maxRows} filas.");
                }
            }

            return ['headers' => $headers, 'rows' => $rows];
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @param resource $stream
     * @return array{headers: list<string>, delimiter: string}
     */
    private function readHeaders($stream): array
    {
        $rawLine = fgets($stream);
        if ($rawLine === false) {
            throw new DailyScheduleCsvHeaderException('El archivo CSV no contiene encabezados.');
        }

        [$rawHeaders, $delimiter] = $this->parseCsvLine($rawLine);
        $headers = array_map(fn ($header): string => $this->cleanHeader((string) $header), $rawHeaders);
        $expected = DailyScheduleCsvSchema::headers();
        if ($this->isHorizontalTemplate($headers)) {
            $headers = $this->normalizeHorizontalHeaders($headers);

            return ['headers' => $headers, 'delimiter' => $delimiter];
        }

        $duplicates = array_values(array_unique(array_diff_assoc($headers, array_unique($headers))));
        if ($duplicates !== []) {
            throw new DailyScheduleCsvHeaderException('El archivo CSV contiene encabezados duplicados: '.implode(', ', $duplicates).'.');
        }

        $missing = array_values(array_diff($expected, $headers));
        $unknown = array_values(array_diff($headers, $expected));
        if ($missing !== [] || $unknown !== [] || $headers !== $expected) {
            throw new DailyScheduleCsvHeaderException('El archivo CSV debe usar exactamente los encabezados de la version 1.');
        }

        return ['headers' => $headers, 'delimiter' => $delimiter];
    }

    /**
     * @return array{0: list<string>, 1: string}
     */
    private function parseCsvLine(string $line): array
    {
        $candidates = [',', "\t", ';'];
        $bestDelimiter = ',';
        $bestValues = str_getcsv($line, ',');

        foreach ($candidates as $delimiter) {
            $values = str_getcsv($line, $delimiter);
            if (count($values) > count($bestValues)) {
                $bestDelimiter = $delimiter;
                $bestValues = $values;
            }
        }

        return [$bestValues, $bestDelimiter];
    }

    /**
     * @param list<string> $headers
     */
    private function isHorizontalTemplate(array $headers): bool
    {
        return array_slice($headers, 0, 2) === DailyScheduleCsvSchema::HORIZONTAL_PREFIX_HEADERS;
    }

    /**
     * @param list<string> $headers
     */
    private function normalizeHorizontalHeaders(array $headers): array
    {
        if (count($headers) < 3) {
            throw new DailyScheduleCsvHeaderException('La plantilla horizontal requiere al menos una fecha.');
        }

        $normalized = [
            $headers[0],
            $headers[1],
            ...array_map(fn (string $date): string => $this->normalizeHorizontalDateHeader($date), array_slice($headers, 2)),
        ];

        $duplicates = array_values(array_unique(array_diff_assoc($normalized, array_unique($normalized))));
        if ($duplicates !== []) {
            throw new DailyScheduleCsvHeaderException('El archivo CSV contiene encabezados duplicados: '.implode(', ', $duplicates).'.');
        }

        return $normalized;
    }

    private function normalizeHorizontalDateHeader(string $date): string
    {
        $date = trim($date);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)
            && checkdate((int) $matches[2], (int) $matches[1], (int) $matches[3])) {
            return "{$matches[3]}-{$matches[2]}-{$matches[1]}";
        }

        throw new DailyScheduleCsvHeaderException('La plantilla horizontal debe usar fechas con formato YYYY-MM-DD o DD/MM/YYYY.');
    }

    /**
     * @param array<string, string> $row
     * @param list<string> $headers
     * @return list<array{row_number: int, data: array<string, string>}>
     */
    private function expandHorizontalRow(int $rowNumber, array $row, array $headers): array
    {
        $employeeCode = $row['codigo_empleado'] ?? '';
        $workerName = $row['nombre_trabajador'] ?? '';
        $expanded = [];

        foreach (array_values(array_slice($headers, 2)) as $dateIndex => $date) {
            $cellValue = trim((string) ($row[$date] ?? ''));
            if ($cellValue === '' || $cellValue === '-') {
                continue;
            }

            $isRestDay = mb_strtoupper($cellValue) === 'DESCANSO';

            $expanded[] = [
                'row_number' => ($rowNumber * 1000) + $dateIndex + 1,
                'data' => [
                    'clave_empleado' => $employeeCode,
                    'fecha' => $date,
                    'tipo_dia' => $isRestDay ? 'descanso' : 'turno',
                    'codigo_turno' => $isRestDay ? '' : $cellValue,
                    'minutos_requeridos' => '',
                    'inicio_ventana' => '',
                    'fin_ventana' => '',
                    'offset_inicio_ventana' => '',
                    'offset_fin_ventana' => '',
                    'inicio_disponibilidad' => '',
                    'fin_disponibilidad' => '',
                    'offset_inicio_disponibilidad' => '',
                    'offset_fin_disponibilidad' => '',
                    'maximo_minutos_trabajo' => '',
                    'motivo' => blank($workerName) ? 'Carga por plantilla horizontal.' : "Carga por plantilla horizontal: {$workerName}.",
                ],
            ];
        }

        return $expanded;
    }

    private function cleanHeader(string $value): string
    {
        return trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value);
    }

    private function cleanValue(string $value): string
    {
        return trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value);
    }

    /**
     * @param list<string> $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function assertSafeText(string $value, int $rowNumber): void
    {
        if (str_contains($value, "\0") || ! mb_check_encoding($value, 'UTF-8')) {
            throw new InvalidDailyScheduleCsvException("La fila {$rowNumber} contiene texto no valido.");
        }
    }
}
