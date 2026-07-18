<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\ShiftTemplate;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

class BuildDailyScheduleSegmentsFromShiftTemplateAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(ShiftTemplate $template, string $workDate, string $timezone): array
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('La zona horaria del centro no es valida.');
        }

        $segments = [];
        $order = 1;

        foreach ($template->segments()->orderBy('sort_order')->get() as $segment) {
            $payload = [
                'segment_order' => $order++,
                'segment_type' => $segment->segment_type,
                'timing_mode' => $segment->timing_mode,
                'start_local_time' => $segment->start_local_time,
                'end_local_time' => $segment->end_local_time,
                'start_day_offset' => (int) $segment->start_day_offset,
                'end_day_offset' => (int) $segment->end_day_offset,
                'starts_at_utc' => null,
                'ends_at_utc' => null,
                'duration_minutes' => $segment->duration_minutes,
                'is_paid' => (bool) $segment->is_paid,
                'shift_template_segment_id' => $segment->id,
                'metadata' => $segment->metadata ?? [],
            ];

            if ($segment->timing_mode === 'fixed') {
                $payload['starts_at_utc'] = $this->localToUtc(
                    $workDate,
                    (string) $segment->start_local_time,
                    (int) $segment->start_day_offset,
                    $timezone,
                );
                $payload['ends_at_utc'] = $this->localToUtc(
                    $workDate,
                    (string) $segment->end_local_time,
                    (int) $segment->end_day_offset,
                    $timezone,
                );
            }

            $segments[] = $payload;
        }

        return $segments;
    }

    private function localToUtc(string $workDate, string $time, int $dayOffset, string $timezone): string
    {
        return CarbonImmutable::parse($workDate, $timezone)
            ->addDays($dayOffset)
            ->setTimeFromTimeString($time)
            ->utc()
            ->toDateTimeString();
    }
}
