<?php

namespace App\Domains\WorkDays\Support;

use App\Models\WorkDayCalculation;

class WorkDayTimeBreakdown
{
    public static function weeklyBase(?WorkDayCalculation $calculation): int
    {
        return (int) data_get($calculation?->result_snapshot, 'ordinary_overtime.weekly_base_minutes', $calculation?->ordinary_minutes ?? 0);
    }

    public static function dailyExtra(?WorkDayCalculation $calculation): int
    {
        return (int) data_get($calculation?->result_snapshot, 'ordinary_overtime.daily_overtime_minutes', $calculation?->overtime_minutes ?? 0);
    }

    public static function weeklyExtra(?WorkDayCalculation $calculation): int
    {
        return (int) data_get($calculation?->result_snapshot, 'ordinary_overtime.weekly_overtime_minutes', 0);
    }
}
