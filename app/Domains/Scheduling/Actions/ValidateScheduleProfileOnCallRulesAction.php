<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Support\LocalTimeWindow;
use InvalidArgumentException;

class ValidateScheduleProfileOnCallRulesAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(array $rules): array
    {
        if (count($rules) !== 7) {
            throw new InvalidArgumentException('Un perfil bajo demanda requiere exactamente siete reglas.');
        }

        $normalized = [];
        $seen = [];
        $hasOnCall = false;

        foreach ($rules as $rule) {
            $day = (int) ($rule['day_of_week'] ?? 0);
            if ($day < 1 || $day > 7 || in_array($day, $seen, true)) {
                throw new InvalidArgumentException('Las reglas bajo demanda deben cubrir dias ISO 1 a 7 sin duplicados.');
            }
            $seen[] = $day;

            $dayType = $rule['day_type'] ?? null;
            if (! in_array($dayType, ['on_call', 'rest'], true)) {
                throw new InvalidArgumentException('El tipo de dia bajo demanda no es valido.');
            }

            if ($dayType === 'rest') {
                $normalized[] = $this->restRule($day, $rule['metadata'] ?? []);
                continue;
            }

            $start = $this->normalizeTime($rule['availability_start_local_time'] ?? null);
            $end = $this->normalizeTime($rule['availability_end_local_time'] ?? null);
            $startOffset = (int) ($rule['availability_start_day_offset'] ?? 0);
            $endOffset = (int) ($rule['availability_end_day_offset'] ?? 0);
            $maxWorkMinutes = (int) ($rule['max_work_minutes'] ?? 0);

            if ($maxWorkMinutes < 1 || $maxWorkMinutes > 1440) {
                throw new InvalidArgumentException('El maximo bajo demanda debe estar entre 1 y 1440 minutos.');
            }
            if (! in_array($startOffset, [0, 1], true) || ! in_array($endOffset, [0, 1], true)) {
                throw new InvalidArgumentException('Los offsets bajo demanda solo pueden ser 0 o 1.');
            }
            LocalTimeWindow::assertValidWindow($start, $end, $startOffset, $endOffset, 'La disponibilidad bajo demanda no es valida.');
            $hasOnCall = true;

            $normalized[] = [
                'day_of_week' => $day,
                'day_type' => 'on_call',
                'availability_start_local_time' => $start,
                'availability_end_local_time' => $end,
                'availability_start_day_offset' => $startOffset,
                'availability_end_day_offset' => $endOffset,
                'max_work_minutes' => $maxWorkMinutes,
                'metadata' => $rule['metadata'] ?? [],
            ];
        }

        if (! $hasOnCall) {
            throw new InvalidArgumentException('Un perfil bajo demanda requiere al menos un dia disponible.');
        }

        usort($normalized, fn (array $a, array $b): int => $a['day_of_week'] <=> $b['day_of_week']);

        return $normalized;
    }

    private function restRule(int $day, mixed $metadata): array
    {
        return [
            'day_of_week' => $day,
            'day_type' => 'rest',
            'availability_start_local_time' => null,
            'availability_end_local_time' => null,
            'availability_start_day_offset' => 0,
            'availability_end_day_offset' => 0,
            'max_work_minutes' => null,
            'metadata' => $metadata,
        ];
    }

    private function normalizeTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException('La disponibilidad bajo demanda requiere hora inicial y final.');
        }

        LocalTimeWindow::timeToMinutes((string) $value);

        return strlen((string) $value) === 5 ? ((string) $value).':00' : (string) $value;
    }
}
