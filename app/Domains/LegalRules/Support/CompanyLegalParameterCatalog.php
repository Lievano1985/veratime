<?php

namespace App\Domains\LegalRules\Support;

class CompanyLegalParameterCatalog
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            'company_daily_limit_diurnal_minutes' => [
                'label' => 'Limite interno diurno',
                'description' => 'Puede ser igual o menor al limite base de 8 horas.',
                'unit' => 'minutes',
                'value_key' => 'minutes',
                'default' => 480,
                'min' => 1,
                'max' => 480,
                'protected_max' => true,
            ],
            'company_daily_limit_nocturnal_minutes' => [
                'label' => 'Limite interno nocturno',
                'description' => 'Puede ser igual o menor al limite base de 7 horas.',
                'unit' => 'minutes',
                'value_key' => 'minutes',
                'default' => 420,
                'min' => 1,
                'max' => 420,
                'protected_max' => true,
            ],
            'company_daily_limit_mixed_minutes' => [
                'label' => 'Limite interno mixto',
                'description' => 'Puede ser igual o menor al limite base de 7 horas 30 minutos.',
                'unit' => 'minutes',
                'value_key' => 'minutes',
                'default' => 450,
                'min' => 1,
                'max' => 450,
                'protected_max' => true,
            ],
            'company_weekly_limit_minutes' => [
                'label' => 'Limite interno semanal',
                'description' => 'Puede ser igual o menor al limite base de 48 horas.',
                'unit' => 'minutes',
                'value_key' => 'minutes',
                'default' => 2880,
                'min' => 1,
                'max' => 2880,
                'protected_max' => true,
            ],
            'late_arrival_tolerance_minutes' => [
                'label' => 'Tolerancia de entrada',
                'description' => 'Politica operativa interna para revision de retardos.',
                'unit' => 'minutes',
                'value_key' => 'minutes',
                'default' => 0,
                'min' => 0,
                'max' => 30,
                'protected_max' => false,
            ],
            'early_departure_tolerance_minutes' => [
                'label' => 'Tolerancia de salida anticipada',
                'description' => 'Politica operativa interna para revision de salidas anticipadas.',
                'unit' => 'minutes',
                'value_key' => 'minutes',
                'default' => 0,
                'min' => 0,
                'max' => 30,
                'protected_max' => false,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $code): array
    {
        $definitions = $this->all();

        if (! array_key_exists($code, $definitions)) {
            throw new \InvalidArgumentException('Parametro legal de empresa no permitido.');
        }

        return $definitions[$code];
    }
}
