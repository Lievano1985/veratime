<?php

use App\Domains\Scheduling\Actions\ValidateShiftTemplateSegmentsAction;
use App\Domains\Scheduling\Support\ShiftTemplateTimeline;

it('calculates split, continuous and overnight schedules without double subtracting breaks', function (array $segments, int $work, int $span, int $unpaid) {
    $normalized = (new ValidateShiftTemplateSegmentsAction)->handle($segments);
    $metrics = ShiftTemplateTimeline::fromSegments($normalized)->metrics();
    expect($metrics['effective_work_minutes'])->toBe($work)
        ->and($metrics['total_span_minutes'])->toBe($span)
        ->and($metrics['unpaid_break_minutes'])->toBe($unpaid);
})->with(function () {
    $fixed = fn ($type, $start, $end, $order, $startDay = 0, $endDay = 0, $paid = false) => ['segment_type' => $type, 'timing_mode' => 'fixed', 'start_local_time' => $start, 'end_local_time' => $end, 'start_day_offset' => $startDay, 'end_day_offset' => $endDay, 'sort_order' => $order, 'is_paid' => $paid];
    $duration = fn ($minutes, $paid) => ['segment_type' => 'break', 'timing_mode' => 'duration', 'duration_minutes' => $minutes, 'is_paid' => $paid, 'sort_order' => 2];

    return [
        '09-19 with two hour fixed break' => [[$fixed('work', '09:00', '13:30', 1), $fixed('break', '13:30', '15:30', 2), $fixed('work', '15:30', '19:00', 3)], 480, 600, 120],
        '08-17 with one hour unpaid duration' => [[$fixed('work', '08:00', '17:00', 1), $duration(60, false)], 480, 540, 60],
        '08-17 with one hour paid duration' => [[$fixed('work', '08:00', '17:00', 1), $duration(60, true)], 540, 540, 0],
        'overnight unpaid duration' => [[$fixed('work', '22:00', '07:00', 1, 0, 1), $duration(60,false)], 480, 540, 60],
    ];
});
