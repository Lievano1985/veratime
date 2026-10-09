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
            'daily_limit_exceeded' => [
                'name' => 'Jornada excedida',
                'description' => 'La duración trabajada supera el límite diario aplicable.',
                'default_severity' => AlertType::SEVERITY_WARNING,
                'category' => 'daily',
            ],
            'daily_overtime_over_three_hours' => [
                'name' => 'Tiempo extra diario superior a tres horas',
                'description' => 'La jornada acumula más de tres horas extraordinarias.',
                'default_severity' => AlertType::SEVERITY_WARNING,
                'category' => 'daily',
            ],
            'long_work_day' => [
                'name' => 'Jornada larga',
                'description' => 'La jornada trabajada supera diez horas y requiere revisión.',
                'default_severity' => AlertType::SEVERITY_WARNING,
                'category' => 'daily',
            ],
            'minimum_break_missing' => [
                'name' => 'Pausa mínima no identificada',
                'description' => 'No se identificó una pausa acumulada de al menos treinta minutos.',
                'default_severity' => AlertType::SEVERITY_WARNING,
                'category' => 'rest',
            ],
            'late_arrival_detected' => [
                'name' => 'Retardo',
                'description' => 'La jornada tiene minutos de retardo calculados.',
                'default_severity' => AlertType::SEVERITY_WARNING,
                'category' => 'daily',
            ],
            'early_departure_detected' => [
                'name' => 'Salida anticipada',
                'description' => 'La jornada tiene minutos de salida anticipada calculados.',
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
            'scheduled_rest_work' => [
                'name' => 'Trabajo en día de descanso asignado',
                'description' => 'La jornada registra trabajo en un día publicado como descanso.',
                'default_severity' => AlertType::SEVERITY_HIGH,
                'category' => 'rest',
            ],
            'minor_daily_hours_exceeded' => [
                'name' => 'Persona menor con jornada superior a seis horas',
                'description' => 'La jornada requiere revisión por la edad registrada de la persona trabajadora.',
                'default_severity' => AlertType::SEVERITY_CRITICAL,
                'category' => 'daily',
            ],
            'minor_restricted_work' => [
                'name' => 'Persona menor con tiempo extra o jornada nocturna',
                'description' => 'La jornada requiere revisión por la edad registrada y sus características.',
                'default_severity' => AlertType::SEVERITY_CRITICAL,
                'category' => 'daily',
            ],
            'weekly_rest_missing' => [
                'name' => 'Semana sin descanso detectado',
                'description' => 'La semana natural no muestra dia de descanso para revision.',
                'default_severity' => AlertType::SEVERITY_HIGH,
                'category' => 'weekly',
            ],
            'weekly_hours_exceeded' => [
                'name' => 'Horas semanales superiores al máximo',
                'description' => 'La suma semanal trabajada supera el límite aplicable.',
                'default_severity' => AlertType::SEVERITY_HIGH,
                'category' => 'weekly',
            ],
            'weekly_overtime_exceeded' => [
                'name' => 'Tiempo extra semanal superior al límite',
                'description' => 'La suma semanal de tiempo extraordinario requiere revisión.',
                'default_severity' => AlertType::SEVERITY_HIGH,
                'category' => 'weekly',
            ],
            'weekly_overtime_days_exceeded' => [
                'name' => 'Más de tres días con tiempo extra',
                'description' => 'La semana concentra tiempo extraordinario en más de tres días.',
                'default_severity' => AlertType::SEVERITY_WARNING,
                'category' => 'weekly',
            ],
            'weekly_sunday_work' => [
                'name' => 'Domingos trabajados en la semana',
                'description' => 'La semana incluye trabajo en domingo para revisión operativa.',
                'default_severity' => AlertType::SEVERITY_INFORMATIONAL,
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
