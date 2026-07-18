<?php

namespace App\Domains\Scheduling\Support;

use InvalidArgumentException;

class LocalTimeWindow
{
    public static function timeToMinutes(string $time): int
    {
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $time, $matches)) {
            throw new InvalidArgumentException('La hora local no tiene formato valido.');
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    public static function absoluteMinutes(string $time, int $dayOffset): int
    {
        if (! in_array($dayOffset, [0, 1], true)) {
            throw new InvalidArgumentException('El desplazamiento de dia solo puede ser 0 o 1.');
        }

        return ($dayOffset * 1440) + self::timeToMinutes($time);
    }

    public static function assertValidWindow(
        string $startTime,
        string $endTime,
        int $startOffset,
        int $endOffset,
        string $message = 'La ventana local no es valida.',
    ): void {
        $start = self::absoluteMinutes($startTime, $startOffset);
        $end = self::absoluteMinutes($endTime, $endOffset);

        if ($end <= $start || ($end - $start) > 1440) {
            throw new InvalidArgumentException($message);
        }
    }
}
