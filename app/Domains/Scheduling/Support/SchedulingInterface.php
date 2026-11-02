<?php

namespace App\Domains\Scheduling\Support;

use App\Models\Company;

class SchedulingInterface
{
    public const PLANNER = 'planner';

    public const CLASSIC = 'classic';

    public static function forCompany(Company $company): string
    {
        $value = $company->setting()->first()?->metadata['scheduling_interface'] ?? self::PLANNER;

        return in_array($value, [self::PLANNER, self::CLASSIC], true) ? $value : self::PLANNER;
    }

    public static function label(string $mode): string
    {
        return $mode === self::CLASSIC ? 'Modelos de Horarios' : 'Planificador de horarios';
    }
}
