<?php

namespace App\Domains\ExportLayouts\Actions;

use App\Domains\ExportLayouts\Support\IncidentCatalog;
use App\Domains\ExportLayouts\Support\LayoutDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class BuildLayoutMatrixAction
{
    /** Build the same matrix for preview and download. Never choose a winning incident silently. */
    public function handle(array $definition, array $sourceRows, string $start, string $end): array
    {
        $definition = LayoutDefinition::validate($definition);
        $from = CarbonImmutable::parse($start)->startOfDay();
        $to = CarbonImmutable::parse($end)->startOfDay();
        if ($to->lessThan($from) || $from->diffInDays($to) > 365 || count($sourceRows) > 100000) {
            throw ValidationException::withMessages(['export' => 'El rango o tamaño excede los límites de este generador (366 días / 100,000 registros).']);
        }
        $dates = [];
        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            $dates[] = $date->toDateString();
        }
        $workers = [];
        $cells = [];
        $seen = [];
        $labels = [];
        $errors = [];
        $omitted = [];
        $rules = collect($definition['rules'])->where('enabled', true)->all();
        foreach ($sourceRows as $index => $row) {
            $code = trim((string) ($row['numero_empleado'] ?? ''));
            $id = (string) ($row['empleado_id'] ?? '');
            $date = (string) ($row['fecha'] ?? '');
            if ($id === '' || $code === '' || ! in_array($date, $dates, true)) {
                $errors[] = 'Registro '.($index + 1).': falta identificación o su fecha no pertenece al período.';

                continue;
            }
            $label = match ($definition['identifier']) {
                'name' => trim((string) ($row['nombre_empleado'] ?? '')),
                'code_name' => $code.' · '.trim((string) ($row['nombre_empleado'] ?? '')),
                default => $code,
            };
            if ($label === '' || (isset($labels[$label]) && $labels[$label] !== $id)) {
                $errors[] = "Identificación vacía o repetida: $code. Usa códigos únicos.";

                continue;
            }
            if (isset($workers[$id]) && $workers[$id] !== $label) {
                $errors[] = "$code: la identificación cambia dentro del período. Revisa los códigos antes de exportar.";

                continue;
            }
            $labels[$label] = $id;
            $workers[$id] = $label;
            $key = $id.'|'.$date;
            if (isset($seen[$key])) {
                $errors[] = "$code · $date: más de un registro de jornada. Revisa el origen antes de combinarlo.";

                continue;
            }
            $seen[$key] = true;
            $tokens = [];
            foreach ($rules as $rule) {
                $item = IncidentCatalog::all()[$rule['key']];
                $quantity = $this->quantity($rule, $item, $row, $errors, "$code · $date");
                if ($quantity <= 0) {
                    continue;
                }
                $rounded = round($quantity, (int) $definition['precision']);
                if ($rounded <= 0) {
                    $errors[] = "$code · $date: la precisión elegida convierte una cantidad positiva en cero.";

                    continue;
                }
                $value = rtrim(rtrim(number_format($rounded, (int) $definition['precision'], '.', ''), '0'), '.');
                if ((int) $definition['precision'] === 0) {
                    $value = (string) (int) $rounded;
                }
                $tokens[] = match ($definition['cell_format']) {
                    'code_only' => $rule['code'],
                    'code_quantity' => $rule['code'].$value,
                    default => $value.$rule['code'],
                };
            }
            if (count($tokens) > 1) {
                $errors[] = "$code · $date: coinciden ".implode(', ', $tokens).'. Este layout admite una sola incidencia por celda.';
            } elseif ($tokens !== []) {
                $cells[$id][$date] = $tokens[0];
            }
            $type = (string) ($row['tipo_incidencia'] ?? '');
            if ($type !== '' && ($row['estatus_incidencia'] ?? '') === 'approved') {
                $active = collect($rules)->pluck('key')->all();
                if (! in_array($type, $active, true)) {
                    $omitted[$type] = ($omitted[$type] ?? 0) + 1;
                }
            }
        }
        if ($definition['only_incident_workers']) {
            $workers = array_filter($workers, fn ($label, $id) => isset($cells[$id]), ARRAY_FILTER_USE_BOTH);
        }
        asort($workers, SORT_NATURAL);
        if (count($workers) > 10000 || (count($workers) + 1) * (count($dates) + 1) > 250000) {
            throw ValidationException::withMessages(['export' => 'La matriz supera 10,000 trabajadores o 250,000 celdas. Elige un período o alcance menor.']);
        }
        $headers = [];
        $rows = [];
        $dateCells = [];
        if ($definition['orientation'] === 'workers_rows') {
            $headers = [match ($definition['identifier']) {
                'name' => 'Trabajador', 'code_name' => 'Código · Trabajador', default => 'Código Empleado'
            }];
            foreach ($dates as $column => $date) {
                $headers[] = CarbonImmutable::parse($date)->format($definition['date_format']);
                $dateCells[] = ['row' => 0, 'column' => $column + 1, 'date' => $date];
            }
            foreach ($workers as $id => $label) {
                $rows[] = array_merge([$label], array_map(fn ($date) => $cells[$id][$date] ?? '', $dates));
            }
        } else {
            $headers = array_merge(['Fecha'], array_values($workers));
            foreach ($dates as $index => $date) {
                $rows[] = array_merge([CarbonImmutable::parse($date)->format($definition['date_format'])], array_map(fn ($id) => $cells[$id][$date] ?? '', array_keys($workers)));
                $dateCells[] = ['row' => $index + 1, 'column' => 0, 'date' => $date];
            }
        }
        $warnings = [];
        foreach ($omitted as $type => $count) {
            $label = IncidentCatalog::all()[$type]['label'] ?? $type;
            $warnings[] = "$label: $count registro(s) fuera de las incidencias activadas en este layout.";
        }

        return ['headers' => $headers, 'rows' => $rows, 'date_cells' => $dateCells,
            'errors' => array_values(array_unique($errors)), 'warnings' => $warnings,
            'worker_count' => count($workers), 'date_count' => count($dates), 'incident_cells' => array_sum(array_map('count', $cells))];
    }

    private function quantity(array $rule, array $item, array $row, array &$errors, string $context): float
    {
        if ($item['kind'] === 'incident') {
            return ($row['tipo_incidencia'] ?? '') === $rule['key'] && ($row['estatus_incidencia'] ?? '') === 'approved' ? 1.0 : 0.0;
        }
        if (in_array($item['kind'], ['flag', 'approved_flag'], true)) {
            if ($item['kind'] === 'approved_flag' && ($row['estatus_incidencia'] ?? '') !== 'approved') {
                return 0;
            }
            $flag = $row[$item['source']] ?? null;
            if (! in_array($flag, ['si', 'no'], true)) {
                $errors[] = "$context: el cierre no conserva un indicador válido para {$item['label']}.";

                return 0;
            }
            if ($flag === 'no') {
                return 0;
            }
            if ($rule['unit'] === 'days') {
                return 1.0;
            }
            $hours = $row[$item['hours_source']] ?? null;
            if (! is_numeric($hours) || (float) $hours <= 0) {
                $errors[] = "$context: el cierre indica {$item['label']}, pero no conserva sus horas válidas.";

                return 0;
            }

            return $rule['unit'] === 'minutes' ? (float) $hours * 60 : (float) $hours;
        }
        if ($item['kind'] === 'worked') {
            foreach (['horas_ordinarias', 'horas_extra_totales', 'horas_extra_dobles', 'horas_extra_triples', 'horas_festivo_trabajado'] as $source) {
                if (is_numeric($row[$source] ?? null) && (float) $row[$source] > 0) {
                    return 1.0;
                }
            }

            return ($row['descanso_trabajado'] ?? '') === 'si' ? 1.0 : 0.0;
        }
        $source = $item['source'];
        if (! array_key_exists($source, $row) || ! is_numeric($row[$source]) || (float) $row[$source] < 0) {
            $errors[] = "$context: no hay una cantidad válida para {$item['label']}.";

            return 0;
        }
        $quantity = (float) $row[$source];
        if ($item['kind'] === 'minutes' && $rule['unit'] === 'hours') {
            $quantity /= 60;
        }
        if ($item['kind'] === 'hours' && $rule['unit'] === 'minutes') {
            $quantity *= 60;
        }

        return $quantity;
    }
}
