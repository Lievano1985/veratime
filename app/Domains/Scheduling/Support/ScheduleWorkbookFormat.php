<?php

namespace App\Domains\Scheduling\Support;

use App\Models\ShiftTemplate;

class ScheduleWorkbookFormat
{
    public const VERSION = 'vera_time_schedule_xlsx_v1';

    public const HEADER_ROW = 5;

    public const FIRST_ROW = 6;

    public const KEEP = 'Conservar horario actual';

    public static function range(ShiftTemplate $turn): string
    {
        $work = $turn->segments->where('segment_type', 'work')->sortBy('sort_order');
        $first = $work->first();
        $last = $work->last();

        return $first && $last
            ? substr($first->start_local_time, 0, 5).'–'.substr($last->end_local_time, 0, 5).($last->end_day_offset ? ' (+1 día)' : '')
            : '';
    }

    public static function label(ShiftTemplate $turn, ?string $range = null): string
    {
        return $turn->code.' · '.$turn->name.' · '.($range ?? self::range($turn));
    }
}
