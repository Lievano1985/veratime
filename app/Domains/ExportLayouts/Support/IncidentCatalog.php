<?php

namespace App\Domains\ExportLayouts\Support;

class IncidentCatalog
{
    /** Codes are editable examples from the user's DYH review, not certified vendor defaults. */
    public static function all(): array
    {
        return [
            'unjustified_absence' => ['label' => 'Falta injustificada', 'code' => 'FINJ', 'unit' => 'days', 'kind' => 'incident', 'available' => true],
            'paid_permission' => ['label' => 'Permiso con goce', 'code' => 'PCS', 'unit' => 'days', 'kind' => 'incident', 'available' => true],
            'unpaid_permission' => ['label' => 'Permiso sin goce', 'code' => 'PSS', 'unit' => 'days', 'kind' => 'incident', 'available' => true],
            'late_arrival' => ['label' => 'Retardo', 'code' => 'RET', 'unit' => 'hours', 'kind' => 'minutes', 'source' => 'minutos_retardo', 'available' => true],
            'overtime_double' => ['label' => 'Horas extra dobles', 'code' => 'HE2', 'unit' => 'hours', 'kind' => 'hours', 'source' => 'horas_extra_dobles', 'available' => true],
            'overtime_triple' => ['label' => 'Horas extra triples', 'code' => 'HE3', 'unit' => 'hours', 'kind' => 'hours', 'source' => 'horas_extra_triples', 'available' => true],
            'worked_day' => ['label' => 'Día trabajado', 'code' => 'TRAB', 'unit' => 'days', 'kind' => 'worked', 'available' => true],
            'vacation' => ['label' => 'Vacaciones', 'code' => '', 'unit' => 'days', 'kind' => 'incident', 'available' => true],
            'justified_paid_absence' => ['label' => 'Ausencia justificada con goce', 'code' => '', 'unit' => 'days', 'kind' => 'incident', 'available' => true],
            'justified_unpaid_absence' => ['label' => 'Ausencia justificada sin goce', 'code' => '', 'unit' => 'days', 'kind' => 'incident', 'available' => true],
            'incapacity' => ['label' => 'Incapacidad (registro general)', 'code' => 'INC', 'unit' => 'days', 'kind' => 'incident', 'available' => true, 'note' => 'Activa solo si el destino admite incapacidades por este medio; este registro no distingue sus subtipos.'],
            'general_illness' => ['label' => 'Enfermedad general', 'code' => 'ENFG', 'unit' => 'days', 'kind' => 'unsupported', 'available' => false, 'note' => 'El origen actual no distingue este subtipo de incapacidad.'],
            'maternity' => ['label' => 'Incapacidad por maternidad', 'code' => 'MAT', 'unit' => 'days', 'kind' => 'unsupported', 'available' => false, 'note' => 'El origen actual agrupa maternidad y paternidad; falta una equivalencia precisa.'],
            'work_accident' => ['label' => 'Accidente de trabajo', 'code' => 'ATRB', 'unit' => 'days', 'kind' => 'unsupported', 'available' => false, 'note' => 'Requiere identificar el subtipo y su destino de carga.'],
            'commuting_accident' => ['label' => 'Accidente de trayecto', 'code' => 'ATRY', 'unit' => 'days', 'kind' => 'unsupported', 'available' => false, 'note' => 'Requiere identificar el subtipo y su destino de carga.'],
            'disciplinary_days' => ['label' => 'Días de castigo', 'code' => 'CAST', 'unit' => 'days', 'kind' => 'unsupported', 'available' => false, 'note' => 'Sin un concepto equivalente en los cierres de Vera Time.'],
            'overtime_single' => ['label' => 'Horas extra sencillas', 'code' => 'HE1', 'unit' => 'hours', 'kind' => 'unsupported', 'available' => false, 'note' => 'Vera Time no calcula una categoría separada de horas extra sencillas.'],
            'overtime_four' => ['label' => 'Horas extra adicionales HE4', 'code' => 'HE4', 'unit' => 'hours', 'kind' => 'unsupported', 'available' => false, 'note' => 'Requiere una categoría de origen confirmada; no se duplican horas.'],
            'overtime_five' => ['label' => 'Horas extra adicionales HE5', 'code' => 'HE5', 'unit' => 'hours', 'kind' => 'unsupported', 'available' => false, 'note' => 'Requiere una categoría de origen confirmada; no se duplican horas.'],
            'maternity_paternity' => ['label' => 'Maternidad / paternidad', 'code' => '', 'unit' => 'days', 'kind' => 'incident', 'available' => true, 'note' => 'Exporta el tipo combinado de Vera Time; no se transforma automáticamente en incapacidad MAT.'],
            'other' => ['label' => 'Otra incidencia registrada', 'code' => '', 'unit' => 'days', 'kind' => 'incident', 'available' => true],
            'sunday_work' => ['label' => 'Domingo trabajado', 'code' => '', 'unit' => 'days', 'kind' => 'flag', 'source' => 'domingo_trabajado', 'hours_source' => 'horas_domingo_trabajado', 'available' => true],
            'sunday_premium' => ['label' => 'Prima dominical aplicable', 'code' => '', 'unit' => 'days', 'kind' => 'flag', 'source' => 'prima_dominical_aplica', 'available' => true, 'note' => 'Indica un día con prima aplicable; no exporta un porcentaje ni un importe monetario.'],
            'scheduled_rest_work' => ['label' => 'Descanso asignado / semanal trabajado', 'code' => '', 'unit' => 'days', 'kind' => 'flag', 'source' => 'descanso_trabajado', 'available' => true],
            'sunday_rest_work' => ['label' => 'Domingo trabajado en descanso', 'code' => '', 'unit' => 'days', 'kind' => 'flag', 'source' => 'descanso_domingo_trabajado', 'available' => true],
            'mandatory_rest_work' => ['label' => 'Festivo / descanso obligatorio trabajado', 'code' => '', 'unit' => 'days', 'kind' => 'flag', 'source' => 'descanso_obligatorio_trabajado', 'hours_source' => 'horas_festivo_trabajado', 'available' => true],
            'scheduled_rest' => ['label' => 'Descanso programado', 'code' => '', 'unit' => 'days', 'kind' => 'flag', 'source' => 'descanso_programado', 'available' => true, 'note' => 'Referencia de programación; no implica que se haya trabajado.'],
            'ordinary_hours' => ['label' => 'Horas ordinarias', 'code' => '', 'unit' => 'hours', 'kind' => 'hours', 'source' => 'horas_ordinarias', 'available' => true],
            'overtime_total' => ['label' => 'Horas extra totales', 'code' => '', 'unit' => 'hours', 'kind' => 'hours', 'source' => 'horas_extra_totales', 'available' => true, 'note' => 'Es el total de extra; no lo sumes nuevamente a sus componentes dobles y triples.'],
            'night_hours' => ['label' => 'Tiempo nocturno', 'code' => '', 'unit' => 'hours', 'kind' => 'hours', 'source' => 'horas_nocturnas', 'available' => true],
            'holiday_normal_hours' => ['label' => 'Festivo trabajado: componente de pago normal (horas)', 'code' => '', 'unit' => 'hours', 'kind' => 'hours', 'source' => 'horas_festivo_pago_normal', 'available' => true, 'note' => 'Cantidad de horas del componente, no un importe de nómina.'],
            'holiday_additional_hours' => ['label' => 'Festivo trabajado: componente adicional (horas)', 'code' => '', 'unit' => 'hours', 'kind' => 'hours', 'source' => 'horas_festivo_pago_doble_adicional', 'available' => true, 'note' => 'Cantidad de horas del componente, sin multiplicar ni recalcular el pago.'],
            'paid_break' => ['label' => 'Pausa computable / pagada', 'code' => '', 'unit' => 'hours', 'kind' => 'hours', 'source' => 'horas_descanso_pagado', 'available' => true],
            'unpaid_break' => ['label' => 'Pausa no computable / no pagada', 'code' => '', 'unit' => 'hours', 'kind' => 'hours', 'source' => 'horas_descanso_no_pagado', 'available' => true],
            'early_departure' => ['label' => 'Salida anticipada', 'code' => '', 'unit' => 'minutes', 'kind' => 'minutes', 'source' => 'minutos_salida_anticipada', 'available' => true],
            'absence_time' => ['label' => 'Tiempo de ausencia', 'code' => '', 'unit' => 'minutes', 'kind' => 'minutes', 'source' => 'minutos_ausencia', 'available' => true, 'note' => 'Tiempo registrado de ausencia; no califica automáticamente una falta como injustificada.'],
            'incident_day' => ['label' => 'Día con incidencia o aviso', 'code' => '', 'unit' => 'days', 'kind' => 'flag', 'source' => 'tiene_incidencia', 'available' => true, 'note' => 'Marca el indicador del cierre; no sustituye el detalle completo de los avisos operativos.'],
            'paid_incident' => ['label' => 'Incidencia aprobada con goce', 'code' => '', 'unit' => 'days', 'kind' => 'approved_flag', 'source' => 'incidencia_pagada', 'available' => true],
            'unpaid_incident' => ['label' => 'Incidencia aprobada sin goce', 'code' => '', 'unit' => 'days', 'kind' => 'approved_flag', 'source' => 'incidencia_no_pagada', 'available' => true],

        ];
    }

    public static function rules(bool $example = true): array
    {
        $enabled = ['unjustified_absence', 'paid_permission', 'unpaid_permission', 'late_arrival', 'overtime_double', 'overtime_triple'];
        $rules = [];
        foreach (self::all() as $key => $item) {
            $rules[] = ['key' => $key, 'enabled' => $example && in_array($key, $enabled, true), 'code' => $item['code'], 'unit' => $item['unit']];
        }

        return $rules;
    }

    public static function completeRules(array $rules): array
    {
        $present = array_column($rules, 'key');
        foreach (self::rules(false) as $rule) {
            if (! in_array($rule['key'], $present, true)) {
                $rules[] = $rule;
            }
        }

        return $rules;
    }

    public static function units(array $item): array
    {
        if (isset($item['hours_source'])) {
            return ['days' => 'Días', 'hours' => 'Horas', 'minutes' => 'Minutos'];
        }

        return in_array($item['kind'], ['hours', 'minutes'], true) ? ['hours' => 'Horas', 'minutes' => 'Minutos'] : ['days' => 'Días'];
    }
}
