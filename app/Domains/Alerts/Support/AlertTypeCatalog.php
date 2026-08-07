<?php

namespace App\Domains\Alerts\Support;

use App\Models\AlertType;

class AlertTypeCatalog
{
    /**
     * @return array<string, array{name: string, description: string, default_severity: string, category: string}>
     */
    public static function entries(): array
    {
        return [
            'scheduled_absence' => [
                'name' => 'Falta',
                'description' => 'La jornada estaba programada y no tiene eventos validos.',
                'default_severity' => AlertType::SEVERITY_HIGH,
                'category' => 'attendance',
            ],
            'incomplete_work_day' => [
                'name' => 'Jornada incompleta',
                'description' => 'La secuencia de eventos requiere revision operativa.',
                'default_severity' => AlertType::SEVERITY_HIGH,
                'category' => 'event',
            ],
            'overtime_detected' => [
                'name' => 'Tiempo extra detectado',
                'description' => 'La jornada tiene minutos extraordinarios calculados.',
                'default_severity' => AlertType::SEVERITY_WARNING,
                'category' => 'daily',
            ],
            'twelve_hours_exceeded' => [
                'name' => 'Jornada mayor a 12 horas',
                'description' => 'El total trabajado supera 12 horas en una jornada.',
                'default_severity' => AlertType::SEVERITY_CRITICAL,
                'category' => 'daily',
            ],
            'sunday_work' => [
                'name' => 'Trabajo en domingo',
                'description' => 'La jornada incluye minutos trabajados en domingo.',
                'default_severity' => AlertType::SEVERITY_INFORMATIONAL,
                'category' => 'rest',
            ],
            'mandatory_rest_work' => [
                'name' => 'Trabajo en descanso obligatorio',
                'description' => 'La jornada incluye minutos trabajados en descanso obligatorio.',
                'default_severity' => AlertType::SEVERITY_HIGH,
                'category' => 'rest',
            ],
            'weekly_rest_missing' => [
                'name' => 'Semana sin descanso detectado',
                'description' => 'La semana natural no muestra dia de descanso para revision.',
                'default_severity' => AlertType::SEVERITY_HIGH,
                'category' => 'weekly',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function managedCodes(): array
    {
        return array_keys(self::entries());
    }
}
