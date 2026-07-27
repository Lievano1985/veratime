<?php

namespace App\Domains\Scheduling\Support;

class DailyScheduleCsvSchema
{
    public const VERSION = 1;

    public const HORIZONTAL_PREFIX_HEADERS = [
        'codigo_empleado',
        'nombre_trabajador',
    ];

    public const HEADERS = [
        'clave_empleado',
        'fecha',
        'tipo_dia',
        'codigo_turno',
        'minutos_requeridos',
        'inicio_ventana',
        'fin_ventana',
        'offset_inicio_ventana',
        'offset_fin_ventana',
        'inicio_disponibilidad',
        'fin_disponibilidad',
        'offset_inicio_disponibilidad',
        'offset_fin_disponibilidad',
        'maximo_minutos_trabajo',
        'motivo',
    ];

    public const DAY_TYPE_MAP = [
        'turno' => 'shift',
        'descanso' => 'rest',
        'flexible' => 'flexible',
        'guardia' => 'on_call',
        'pendiente' => 'unassigned',
    ];

    public static function headers(): array
    {
        return self::HEADERS;
    }

    /**
     * @param list<string> $dates
     * @return list<string>
     */
    public static function horizontalHeaders(array $dates): array
    {
        return [...self::HORIZONTAL_PREFIX_HEADERS, ...$dates];
    }
}
