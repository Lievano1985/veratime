<?php

namespace App\Domains\ExportLayouts\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LayoutDefinition
{
    public static function defaults(bool $example = true): array
    {
        return [
            'schema_version' => 1, 'name' => $example ? 'NomiPAQ DYH' : '', 'application' => $example ? 'CONTPAQi Nóminas' : '',
            'status' => 'active', 'format' => 'xlsx', 'orientation' => 'workers_rows',
            'identifier' => 'code', 'date_format' => 'd/m/Y', 'delimiter' => 'tab',
            'cell_format' => 'quantity_code', 'precision' => 2, 'only_incident_workers' => true,
            'rules' => IncidentCatalog::rules($example),
        ];
    }

    public static function withCurrentCatalog(array $data): array
    {
        $data['rules'] = IncidentCatalog::completeRules($data['rules'] ?? []);

        return $data;
    }

    public static function validate(array $data): array
    {
        $validated = Validator::make(['form' => $data], [
            'form.schema_version' => ['required', Rule::in([1])],
            'form.name' => ['required', 'string', 'max:120'],
            'form.application' => ['nullable', 'string', 'max:120'],
            'form.status' => ['required', Rule::in(['active', 'inactive'])],
            'form.format' => ['required', Rule::in(['xlsx', 'csv', 'txt'])],
            'form.orientation' => ['required', Rule::in(['workers_rows', 'dates_rows'])],
            'form.identifier' => ['required', Rule::in(['code', 'name', 'code_name'])],
            'form.date_format' => ['required', Rule::in(['d/m/Y', 'Y-m-d', 'd/m'])],
            'form.delimiter' => ['required', Rule::in([',', ';', 'tab', '|'])],
            'form.cell_format' => ['required', Rule::in(['quantity_code', 'code_quantity', 'code_only'])],
            'form.precision' => ['required', 'integer', 'between:0,4'],
            'form.only_incident_workers' => ['required', 'boolean'],
            'form.rules' => ['required', 'array', 'min:1', 'max:'.count(IncidentCatalog::all())],
            'form.rules.*.key' => ['required', 'distinct', Rule::in(array_keys(IncidentCatalog::all()))],
            'form.rules.*.enabled' => ['required', 'boolean'],
            'form.rules.*.code' => ['nullable', 'string', 'max:20', 'regex:/\A[A-Z][A-Z0-9_]*\z/'],
            'form.rules.*.unit' => ['required', Rule::in(['days', 'hours', 'minutes'])],
        ], [
            'form.rules.*.code.regex' => 'Usa una nomenclatura que comience con letra, en mayúsculas, sin espacios.',
        ], [
            'form.name' => 'nombre del layout', 'form.application' => 'aplicación destino',
            'form.status' => 'estado del layout', 'form.format' => 'tipo de archivo',
            'form.orientation' => 'distribución', 'form.identifier' => 'identificación del trabajador',
            'form.date_format' => 'formato de fecha', 'form.delimiter' => 'separador',
            'form.cell_format' => 'contenido de celda', 'form.precision' => 'decimales',
            'form.only_incident_workers' => 'selección de trabajadores', 'form.rules' => 'incidencias',
            'form.rules.*.key' => 'concepto de origen', 'form.rules.*.enabled' => 'selección de incidencia',
            'form.rules.*.code' => 'nomenclatura', 'form.rules.*.unit' => 'unidad',
        ])->validate()['form'];
        $codes = [];
        $enabled = 0;
        foreach ($validated['rules'] as $index => $rule) {
            $item = IncidentCatalog::all()[$rule['key']];
            if (! $rule['enabled']) {
                continue;
            }
            $enabled++;
            if (! $item['available']) {
                throw ValidationException::withMessages(["form.rules.$index.enabled" => $item['note']]);
            }
            if (! in_array($rule['unit'], array_keys(IncidentCatalog::units($item)), true)) {
                throw ValidationException::withMessages(["form.rules.$index.unit" => 'La unidad no corresponde al concepto de origen.']);
            }
            if (empty($rule['code']) || in_array($rule['code'], $codes, true)) {
                throw ValidationException::withMessages(["form.rules.$index.code" => 'Cada incidencia activa necesita un código único.']);
            }
            $codes[] = $rule['code'];
        }
        if ($enabled === 0) {
            throw ValidationException::withMessages(['form.rules' => 'Activa al menos una incidencia para guardar o exportar.']);
        }

        return self::withCurrentCatalog($validated);
    }
}
