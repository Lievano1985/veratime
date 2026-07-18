<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Support\LocalTimeWindow;
use InvalidArgumentException;

class ValidateScheduleProfileFlexibleRulesAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(array $rules): array
    {
        if (count($rules) !== 7) {
            throw new InvalidArgumentException('Un perfil flexible requiere exactamente siete reglas.');
        }

        $normalized = [];
        $seen = [];
        $hasWork = false;

        foreach ($rules as $rule) {
            $day = (int) ($rule['day_of_week'] ?? 0);
            if ($day < 1 || $day > 7 || in_array($day, $seen, true)) {
                throw new InvalidArgumentException('Las reglas flexibles deben cubrir dias ISO 1 a 7 sin duplicados.');
            }
            $seen[] = $day;

            $dayType = $rule['day_type'] ?? null;
            if (! in_array($dayType, ['work', 'rest'], true)) {
                throw new InvalidArgumentException('El tipo de dia flexible no es valido.');
            }

            if ($dayType === 'rest') {
                $normalized[] = $this->restRule($day, $rule['metadata'] ?? []);
                continue;
            }

            $requiredMinutes = (int) ($rule['required_minutes'] ?? 0);
            if ($requiredMinutes < 1 || $requiredMinutes > 1440) {
                throw new InvalidArgumentException('Un dia flexible de trabajo requiere minutos entre 1 y 1440.');
            }
            $hasWork = true;

            $start = $this->normalizeNullableTime($rule['window_start_local_time'] ?? null);
            $end = $this->normalizeNullableTime($rule['window_end_local_time'] ?? null);
            $startOffset = (int) ($rule['window_start_day_offset'] ?? 0);
            $endOffset = (int) ($rule['window_end_day_offset'] ?? 0);
            if (($start === null) !== ($end === null)) {
                throw new InvalidArgumentException('La ventana flexible requiere hora inicial y final.');
            }
            if (! in_array($startOffset, [0, 1], true) || ! in_array($endOffset, [0, 1], true)) {
                throw new InvalidArgumentException('Los offsets flexibles solo pueden ser 0 o 1.');
            }
            if ($start !== null && $end !== null) {
                LocalTimeWindow::assertValidWindow($start, $end, $startOffset, $endOffset, 'La ventana flexible no es valida.');
            } else {
                $startOffset = 0;
                $endOffset = 0;
            }

            $normalized[] = [
                'day_of_week' => $day,
                'day_type' => 'work',
                'required_minutes' => $requiredMinutes,
                'window_start_local_time' => $start,
                'window_end_local_time' => $end,
                'window_start_day_offset' => $startOffset,
                'window_end_day_offset' => $endOffset,
                'metadata' => $rule['metadata'] ?? [],
            ];
        }

        if (! $hasWork) {
            throw new InvalidArgumentException('Un perfil flexible requiere al menos un dia de trabajo.');
        }

        usort($normalized, fn (array $a, array $b): int => $a['day_of_week'] <=> $b['day_of_week']);

        return $normalized;
    }

    private function restRule(int $day, mixed $metadata): array
    {
        return [
            'day_of_week' => $day,
            'day_type' => 'rest',
            'required_minutes' => null,
            'window_start_local_time' => null,
            'window_end_local_time' => null,
            'window_start_day_offset' => 0,
            'window_end_day_offset' => 0,
            'metadata' => $metadata,
        ];
    }

    private function normalizeNullableTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        LocalTimeWindow::timeToMinutes((string) $value);

        return strlen((string) $value) === 5 ? ((string) $value).':00' : (string) $value;
    }
}
