<?php

namespace App\Domains\ExportLayouts\Support;

class LayoutExample
{
    public static function rows(): array
    {
        $rows = [];
        $events = [
            ['1', '0001', 'Andrea Demo', '2026-10-12', 'unjustified_absence', '0', '0', 0],
            ['1', '0001', 'Andrea Demo', '2026-10-13', '', '2.5', '0', 0],
            ['1', '0001', 'Andrea Demo', '2026-10-15', 'paid_permission', '0', '0', 0],
            ['2', '0002', 'Bruno Demo', '2026-10-12', 'unpaid_permission', '0', '0', 0],
            ['2', '0002', 'Bruno Demo', '2026-10-14', '', '0', '0', 30],
            ['2', '0002', 'Bruno Demo', '2026-10-16', '', '0', '1', 0],
            ['1', '0001', 'Andrea Demo', '2026-10-18', '', '0', '0', 0],
            ['2', '0002', 'Bruno Demo', '2026-10-17', '', '0', '0', 0],
        ];
        foreach ($events as [$id, $code, $name, $date, $type, $double, $triple, $late]) {
            $row = ['empleado_id' => $id, 'numero_empleado' => $code, 'nombre_empleado' => $name,
                'fecha' => $date, 'tipo_incidencia' => $type, 'estatus_incidencia' => $type ? 'approved' : '',
                'horas_extra_dobles' => $double, 'horas_extra_triples' => $triple,
                'minutos_retardo' => $late, 'horas_ordinarias' => $type ? '0' : '8',
                'horas_extra_totales' => (string) ((float) $double + (float) $triple),
                'horas_nocturnas' => '0', 'domingo_trabajado' => 'no', 'horas_domingo_trabajado' => '0',
                'prima_dominical_aplica' => 'no', 'descanso_programado' => 'no', 'descanso_trabajado' => 'no',
                'descanso_domingo_trabajado' => 'no', 'descanso_semanal_trabajado' => 'no',
                'descanso_obligatorio_trabajado' => 'no', 'horas_festivo_trabajado' => '0',
                'horas_festivo_pago_normal' => '0', 'horas_festivo_pago_doble_adicional' => '0',
                'horas_descanso_pagado' => '0', 'horas_descanso_no_pagado' => '0',
                'minutos_salida_anticipada' => 0, 'minutos_ausencia' => $type === 'unjustified_absence' ? 480 : 0,
                'tiene_incidencia' => $type !== '' || (float) $double > 0 || (float) $triple > 0 || $late > 0 ? 'si' : 'no',
                'incidencia_pagada' => $type === 'paid_permission' ? 'si' : 'no',
                'incidencia_no_pagada' => in_array($type, ['unjustified_absence', 'unpaid_permission'], true) ? 'si' : 'no',
            ];
            if ($date === '2026-10-18') {
                $row = array_replace($row, ['domingo_trabajado' => 'si', 'prima_dominical_aplica' => 'si',
                    'horas_domingo_trabajado' => '8', 'descanso_programado' => 'si', 'descanso_trabajado' => 'si',
                    'descanso_domingo_trabajado' => 'si', 'descanso_semanal_trabajado' => 'si']);
            }
            if ($date === '2026-10-17') {
                $row = array_replace($row, ['descanso_obligatorio_trabajado' => 'si', 'horas_festivo_trabajado' => '8',
                    'horas_festivo_pago_normal' => '8', 'horas_festivo_pago_doble_adicional' => '8']);
            }
            if ($date === '2026-10-13') {
                $row = array_replace($row, ['horas_nocturnas' => '2', 'horas_descanso_pagado' => '0.5',
                    'horas_descanso_no_pagado' => '1', 'minutos_salida_anticipada' => 15]);
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
