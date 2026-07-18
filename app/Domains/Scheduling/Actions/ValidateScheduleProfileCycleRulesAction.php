<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ShiftTemplate;
use InvalidArgumentException;

class ValidateScheduleProfileCycleRulesAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Company $company, array $rules): array
    {
        $count = count($rules);
        if ($count < 2 || $count > 366) {
            throw new InvalidArgumentException('Un ciclo requiere entre 2 y 366 dias.');
        }

        $normalized = [];
        $seen = [];
        $hasShift = false;

        foreach ($rules as $rule) {
            $day = (int) ($rule['cycle_day'] ?? 0);
            if ($day < 1 || in_array($day, $seen, true)) {
                throw new InvalidArgumentException('Los dias del ciclo deben iniciar en 1 y no repetirse.');
            }
            $seen[] = $day;

            $dayType = $rule['day_type'] ?? null;
            if (! in_array($dayType, ['shift', 'rest'], true)) {
                throw new InvalidArgumentException('El tipo de dia del ciclo no es valido.');
            }

            $shiftTemplateId = $rule['shift_template_id'] ?? null;
            if ($dayType === 'shift') {
                if (! $shiftTemplateId) {
                    throw new InvalidArgumentException('Un dia de ciclo con turno requiere plantilla activa.');
                }
                if (! ShiftTemplate::query()->where('company_id', $company->id)->where('status', 'active')->whereKey($shiftTemplateId)->exists()) {
                    throw new InvalidArgumentException('La plantilla del ciclo no pertenece a la empresa activa o no esta activa.');
                }
                $hasShift = true;
            } elseif ($shiftTemplateId !== null) {
                throw new InvalidArgumentException('Un dia de descanso del ciclo no debe tener plantilla.');
            }

            $normalized[] = [
                'cycle_day' => $day,
                'day_type' => $dayType,
                'shift_template_id' => $dayType === 'shift' ? $shiftTemplateId : null,
                'metadata' => $rule['metadata'] ?? [],
            ];
        }

        sort($seen);
        if ($seen !== range(1, $count)) {
            throw new InvalidArgumentException('Los dias del ciclo deben ser consecutivos, sin huecos.');
        }
        if (! $hasShift) {
            throw new InvalidArgumentException('Un ciclo requiere al menos un dia con turno.');
        }

        usort($normalized, fn (array $a, array $b): int => $a['cycle_day'] <=> $b['cycle_day']);

        return $normalized;
    }
}
