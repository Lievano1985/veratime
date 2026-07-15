<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Support\ShiftTemplateTimeline;
use InvalidArgumentException;

class ValidateShiftTemplateSegmentsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function handle(array $segments): array
    {
        if ($segments === []) {
            throw new InvalidArgumentException('La plantilla requiere al menos un segmento de trabajo.');
        }

        $normalized = [];
        $sortOrders = [];
        $fixedRanges = [];
        $hasWorkStartingDayZero = false;

        foreach (array_values($segments) as $index => $segment) {
            $row = $this->normalizeSegment($segment, $index);
            $sortOrder = $row['sort_order'];

            if (in_array($sortOrder, $sortOrders, true)) {
                throw new InvalidArgumentException('El orden de los segmentos no puede repetirse.');
            }

            $sortOrders[] = $sortOrder;

            if ($row['segment_type'] === 'work' && $row['start_day_offset'] === 0) {
                $hasWorkStartingDayZero = true;
            }

            if ($row['timing_mode'] === 'fixed') {
                $start = ShiftTemplateTimeline::timeToMinutes($row['start_local_time'], $row['start_day_offset']);
                $end = ShiftTemplateTimeline::timeToMinutes($row['end_local_time'], $row['end_day_offset']);

                if ($end <= $start) {
                    throw new InvalidArgumentException('El final del segmento debe ser posterior al inicio.');
                }

                $fixedRanges[] = ['start' => $start, 'end' => $end, 'sort_order' => $sortOrder];
            }

            $normalized[] = $row;
        }

        if (! collect($normalized)->contains(fn (array $segment) => $segment['segment_type'] === 'work')) {
            throw new InvalidArgumentException('La plantilla requiere al menos un segmento de trabajo.');
        }

        if (! $hasWorkStartingDayZero) {
            throw new InvalidArgumentException('Debe existir al menos un segmento de trabajo que inicie en el dia de referencia.');
        }

        $this->assertNoOverlap($fixedRanges);
        $this->assertMaxSpan($fixedRanges);

        return collect($normalized)->sortBy('sort_order')->values()->all();
    }

    private function normalizeSegment(array $segment, int $index): array
    {
        $type = $segment['segment_type'] ?? null;
        $mode = $segment['timing_mode'] ?? null;
        $startOffset = (int) ($segment['start_day_offset'] ?? 0);
        $endOffset = (int) ($segment['end_day_offset'] ?? 0);
        $sortOrder = (int) ($segment['sort_order'] ?? ($index + 1));

        if (! in_array($type, ['work', 'break'], true)) {
            throw new InvalidArgumentException('El tipo de segmento no es valido.');
        }

        if (! in_array($mode, ['fixed', 'duration'], true)) {
            throw new InvalidArgumentException('La modalidad del segmento no es valida.');
        }

        if (! in_array($startOffset, [0, 1], true) || ! in_array($endOffset, [0, 1], true)) {
            throw new InvalidArgumentException('Los offsets solo pueden ser 0 o 1.');
        }

        if ($endOffset < $startOffset) {
            throw new InvalidArgumentException('El offset final no puede ser menor que el inicial.');
        }

        if ($sortOrder <= 0) {
            throw new InvalidArgumentException('El orden del segmento debe ser mayor que cero.');
        }

        if ($type === 'work' && $mode !== 'fixed') {
            throw new InvalidArgumentException('Los segmentos de trabajo deben usar horario fijo.');
        }

        if ($mode === 'fixed') {
            $start = ShiftTemplateTimeline::normalizeTime($segment['start_local_time'] ?? null);
            $end = ShiftTemplateTimeline::normalizeTime($segment['end_local_time'] ?? null);

            if ($start === null || $end === null) {
                throw new InvalidArgumentException('Los segmentos fijos requieren hora inicial y final.');
            }

            return [
                'segment_type' => $type,
                'timing_mode' => 'fixed',
                'start_local_time' => $start,
                'end_local_time' => $end,
                'start_day_offset' => $startOffset,
                'end_day_offset' => $endOffset,
                'duration_minutes' => null,
                'is_paid' => $type === 'work' ? true : (bool) ($segment['is_paid'] ?? false),
                'is_required' => (bool) ($segment['is_required'] ?? true),
                'sort_order' => $sortOrder,
                'metadata' => $segment['metadata'] ?? [],
            ];
        }

        $duration = (int) ($segment['duration_minutes'] ?? 0);
        if ($type !== 'break' || $duration <= 0) {
            throw new InvalidArgumentException('Las pausas por duracion requieren minutos mayores que cero.');
        }

        return [
            'segment_type' => 'break',
            'timing_mode' => 'duration',
            'start_local_time' => null,
            'end_local_time' => null,
            'start_day_offset' => 0,
            'end_day_offset' => 0,
            'duration_minutes' => $duration,
            'is_paid' => (bool) ($segment['is_paid'] ?? false),
            'is_required' => (bool) ($segment['is_required'] ?? true),
            'sort_order' => $sortOrder,
            'metadata' => $segment['metadata'] ?? [],
        ];
    }

    private function assertNoOverlap(array $ranges): void
    {
        usort($ranges, fn (array $a, array $b) => $a['start'] <=> $b['start']);

        $previous = null;
        foreach ($ranges as $range) {
            if ($previous !== null && $range['start'] < $previous['end']) {
                throw new InvalidArgumentException('Los segmentos fijos no pueden solaparse.');
            }

            $previous = $range;
        }
    }

    private function assertMaxSpan(array $ranges): void
    {
        if ($ranges === []) {
            return;
        }

        $firstStart = min(array_column($ranges, 'start'));
        $lastEnd = max(array_column($ranges, 'end'));

        if (($lastEnd - $firstStart) > 1440) {
            throw new InvalidArgumentException('Una plantilla no puede abarcar mas de 24 horas.');
        }
    }
}
