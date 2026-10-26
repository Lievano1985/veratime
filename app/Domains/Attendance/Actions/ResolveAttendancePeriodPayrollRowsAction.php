<?php

namespace App\Domains\Attendance\Actions;

use App\Models\AttendancePeriod;
use Illuminate\Validation\ValidationException;
use JsonException;

class ResolveAttendancePeriodPayrollRowsAction
{
    /** @return list<array<string, string|int>> */
    public function handle(AttendancePeriod $period): array
    {
        $period = AttendancePeriod::where('company_id', $period->company_id)->findOrFail($period->id);
        if ($period->status !== AttendancePeriod::STATUS_CLOSED) {
            throw ValidationException::withMessages(['payroll_export' => 'Solo se pueden exportar periodos cerrados.']);
        }
        if ($period->snapshot_schema_version !== 'attendance_period.v2') {
            throw ValidationException::withMessages(['payroll_export' => 'Este cierre anterior no conserva el detalle histórico para exportar. Consulta su resumen original; no se sustituirá por datos actuales.']);
        }
        $json = $period->snapshot_canonical_json;
        if (! is_string($json) || ! is_string($period->snapshot_sha256)
            || ! hash_equals($period->snapshot_sha256, hash('sha256', $json))) {
            throw ValidationException::withMessages(['payroll_export' => 'No se pudo verificar la integridad del cierre. No se generó la exportación.']);
        }
        try {
            $snapshot = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['payroll_export' => 'El cierre no contiene una copia histórica válida.']);
        }
        if (! is_array($snapshot)) {
            throw ValidationException::withMessages(['payroll_export' => 'El cierre no contiene una copia histórica válida.']);
        }
        $export = $snapshot['payroll_export'] ?? null;
        if (! is_array($export)
            || ($export['schema_version'] ?? null) !== 'payroll_rows.v1') {
            throw ValidationException::withMessages(['payroll_export' => 'El cierre no contiene un detalle histórico de exportación compatible.']);
        }
        if (($snapshot['company_id'] ?? null) !== $period->company_id || ($snapshot['period_id'] ?? null) !== $period->id
            || ($export['company_id'] ?? null) !== $period->company_id || ($export['period_id'] ?? null) !== $period->id) {
            throw ValidationException::withMessages(['payroll_export' => 'La copia histórica no corresponde a esta empresa y periodo.']);
        }
        $rows = $export['rows'] ?? null;
        $columns = $export['columns'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || ! is_array($columns) || ! array_is_list($columns) || $columns === []) {
            throw ValidationException::withMessages(['payroll_export' => 'El detalle histórico del cierre está incompleto.']);
        }
        foreach ($columns as $column) {
            if (! is_string($column) || $column === '') {
                throw ValidationException::withMessages(['payroll_export' => 'El cierre contiene una columna histórica inválida.']);
            }
        }
        if (count($columns) !== count(array_unique($columns))) {
            throw ValidationException::withMessages(['payroll_export' => 'El cierre contiene columnas históricas duplicadas.']);
        }
        foreach ($rows as $row) {
            if (! is_array($row) || array_keys($row) !== $columns) {
                // Canonical JSON sorts object keys; compare the column sets rather than their order.
                if (! is_array($row) || array_diff($columns, array_keys($row)) !== [] || array_diff(array_keys($row), $columns) !== []) {
                    throw ValidationException::withMessages(['payroll_export' => 'El detalle histórico del cierre está incompleto.']);
                }
            }
            foreach ($row as $value) {
                if (! is_string($value) && ! is_int($value)) {
                    throw ValidationException::withMessages(['payroll_export' => 'El cierre contiene un valor de exportación inválido.']);
                }
            }
        }

        return $rows;
    }
}
