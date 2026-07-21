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
            $headers = $this->readHeaders($stream);
            $rows = [];
            $rowNumber = 1;
            $maxRows = (int) config('imports.daily_schedule_csv.max_rows', 10000);

            while (($values = fgetcsv($stream, 0, ',')) !== false) {
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

                $rows[] = [
                    'row_number' => $rowNumber,
                    'data' => array_combine($headers, $values),
                ];

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
     * @return list<string>
     */
    private function readHeaders($stream): array
    {
        $rawHeaders = fgetcsv($stream, 0, ',');
        if ($rawHeaders === false) {
            throw new DailyScheduleCsvHeaderException('El archivo CSV no contiene encabezados.');
        }

        $headers = array_map(fn ($header): string => $this->cleanHeader((string) $header), $rawHeaders);
        $expected = DailyScheduleCsvSchema::headers();

        $duplicates = array_values(array_unique(array_diff_assoc($headers, array_unique($headers))));
        if ($duplicates !== []) {
            throw new DailyScheduleCsvHeaderException('El archivo CSV contiene encabezados duplicados: '.implode(', ', $duplicates).'.');
        }

        $missing = array_values(array_diff($expected, $headers));
        $unknown = array_values(array_diff($headers, $expected));
        if ($missing !== [] || $unknown !== [] || $headers !== $expected) {
            throw new DailyScheduleCsvHeaderException('El archivo CSV debe usar exactamente los encabezados de la version 1.');
        }

        return $headers;
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
