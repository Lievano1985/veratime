<?php

namespace App\Domains\Scheduling\Support;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class ShiftTemplateTimeline
{
    /**
     * @param Collection<int, mixed> $segments
     */
    public function __construct(private Collection $segments)
    {
    }

    /**
     * @param iterable<int, mixed> $segments
     */
    public static function fromSegments(iterable $segments): self
    {
        return new self(collect($segments));
    }

    public static function timeToMinutes(?string $time, int $dayOffset = 0): ?int
    {
        if ($time === null || $time === '') {
            return null;
        }

        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));

        return ($dayOffset * 1440) + ($hours * 60) + $minutes;
    }

    public static function normalizeTime(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        $time = trim($time);
        if (! preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
            throw new InvalidArgumentException('Las horas deben usar formato HH:MM.');
        }

        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));
        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            throw new InvalidArgumentException('La hora local no es valida.');
        }

        return sprintf('%02d:%02d:00', $hours, $minutes);
    }

    public function metrics(): array
    {
        $fixed = $this->fixedSegments();
        $workMinutes = 0;
        $fixedBreakMinutes = 0;
        $paidBreakMinutes = 0;
        $unpaidBreakMinutes = 0;
        $workSegments = 0;

        foreach ($this->segments as $segment) {
            $type = data_get($segment, 'segment_type');
            $mode = data_get($segment, 'timing_mode');
            $isPaid = (bool) data_get($segment, 'is_paid', false);
            $duration = 0;

            if ($mode === 'fixed') {
                $start = self::timeToMinutes(data_get($segment, 'start_local_time'), (int) data_get($segment, 'start_day_offset', 0));
                $end = self::timeToMinutes(data_get($segment, 'end_local_time'), (int) data_get($segment, 'end_day_offset', 0));
                $duration = max(0, ($end ?? 0) - ($start ?? 0));
            } elseif ($type === 'break') {
                $duration = (int) data_get($segment, 'duration_minutes', 0);
            }

            if ($type === 'work') {
                $workMinutes += $duration;
                $workSegments++;
            } elseif ($type === 'break' && $mode === 'fixed') {
                $fixedBreakMinutes += $duration;
                $isPaid ? $paidBreakMinutes += $duration : $unpaidBreakMinutes += $duration;
            } elseif ($type === 'break' && $mode === 'duration') {
                $isPaid ? $paidBreakMinutes += $duration : $unpaidBreakMinutes += $duration;
            }
        }

        $firstStart = $fixed->min('start');
        $lastEnd = $fixed->max('end');

        return [
            'work_minutes' => $workMinutes,
            'fixed_break_minutes' => $fixedBreakMinutes,
            'paid_break_minutes' => $paidBreakMinutes,
            'unpaid_break_minutes' => $unpaidBreakMinutes,
            'total_span_minutes' => $firstStart === null || $lastEnd === null ? 0 : $lastEnd - $firstStart,
            'crosses_midnight' => $fixed->contains(fn (array $segment) => $segment['end'] > 1440 || $segment['start'] >= 1440),
            'work_segment_count' => $workSegments,
        ];
    }

    private function fixedSegments(): Collection
    {
        return $this->segments
            ->filter(fn ($segment) => data_get($segment, 'timing_mode') === 'fixed')
            ->map(fn ($segment) => [
                'start' => self::timeToMinutes(data_get($segment, 'start_local_time'), (int) data_get($segment, 'start_day_offset', 0)),
                'end' => self::timeToMinutes(data_get($segment, 'end_local_time'), (int) data_get($segment, 'end_day_offset', 0)),
                'sort_order' => (int) data_get($segment, 'sort_order'),
            ])
            ->values();
    }
}
