<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\ScheduleBatchVersionComparisonResult;
use App\Models\DailyScheduleAssignment;
use App\Models\ScheduleBatch;
use InvalidArgumentException;

class CompareScheduleBatchVersionsAction
{
    public function handle(ScheduleBatch $previous, ScheduleBatch $corrected): ScheduleBatchVersionComparisonResult
    {
        $previous = $this->loadBatch($previous);
        $corrected = $this->loadBatch($corrected);

        if ($previous->company_id !== $corrected->company_id
            || $previous->center_id !== $corrected->center_id
            || $previous->period_start->toDateString() !== $corrected->period_start->toDateString()
            || $previous->period_end->toDateString() !== $corrected->period_end->toDateString()
            || $corrected->previous_batch_id !== $previous->id) {
            throw new InvalidArgumentException('Las versiones no pertenecen a la misma cadena correctiva.');
        }

        $before = $previous->dailyAssignments->keyBy(fn (DailyScheduleAssignment $assignment) => $this->key($assignment));
        $after = $corrected->dailyAssignments->keyBy(fn (DailyScheduleAssignment $assignment) => $this->key($assignment));
        $keys = collect($before->keys())->merge($after->keys())->unique()->sort()->values();

        $changed = 0;
        $unchanged = 0;
        $added = 0;
        $removed = 0;
        $changesByDayType = [];
        $changedRelationships = [];
        $differences = [];

        foreach ($keys as $key) {
            $beforeAssignment = $before->get($key);
            $afterAssignment = $after->get($key);

            if (! $beforeAssignment) {
                $added++;
                $differences[] = $this->difference($key, null, $afterAssignment, 'added');
                continue;
            }

            if (! $afterAssignment) {
                $removed++;
                $differences[] = $this->difference($key, $beforeAssignment, null, 'removed');
                continue;
            }

            $beforePayload = $this->functionalPayload($beforeAssignment);
            $afterPayload = $this->functionalPayload($afterAssignment);

            if ($beforePayload === $afterPayload) {
                $unchanged++;
                continue;
            }

            $changed++;
            $changesByDayType[$afterAssignment->day_type] = ($changesByDayType[$afterAssignment->day_type] ?? 0) + 1;
            $changedRelationships[] = $afterAssignment->employment_relationship_id;
            $differences[] = $this->difference($key, $beforeAssignment, $afterAssignment, 'changed', $beforePayload, $afterPayload);
        }

        return new ScheduleBatchVersionComparisonResult(
            previousBatch: $previous,
            correctedBatch: $corrected,
            totalDays: $keys->count(),
            unchangedDays: $unchanged,
            changedDays: $changed,
            addedDays: $added,
            removedDays: $removed,
            changesByDayType: $changesByDayType,
            changedRelationships: array_values(array_unique($changedRelationships)),
            differences: $differences,
        );
    }

    private function loadBatch(ScheduleBatch $batch): ScheduleBatch
    {
        return ScheduleBatch::query()
            ->with(['dailyAssignments.employmentRelationship.worker', 'dailyAssignments.shiftTemplate', 'dailyAssignments.segments'])
            ->findOrFail($batch->id);
    }

    private function functionalPayload(DailyScheduleAssignment $assignment): array
    {
        return [
            'employment_relationship_id' => $assignment->employment_relationship_id,
            'work_date' => $assignment->work_date->toDateString(),
            'organizational_unit_id' => $assignment->organizational_unit_id,
            'day_type' => $assignment->day_type,
            'timezone' => $assignment->timezone,
            'shift_template_id' => $assignment->shift_template_id,
            'source_type' => $assignment->source_type,
            'source_reference' => $this->canonicalize($assignment->source_reference ?? []),
            'required_minutes' => $assignment->required_minutes,
            'window_start_local_time' => $assignment->window_start_local_time,
            'window_end_local_time' => $assignment->window_end_local_time,
            'window_start_day_offset' => $assignment->window_start_day_offset,
            'window_end_day_offset' => $assignment->window_end_day_offset,
            'availability_start_local_time' => $assignment->availability_start_local_time,
            'availability_end_local_time' => $assignment->availability_end_local_time,
            'availability_start_day_offset' => $assignment->availability_start_day_offset,
            'availability_end_day_offset' => $assignment->availability_end_day_offset,
            'max_work_minutes' => $assignment->max_work_minutes,
            'metadata' => $this->canonicalize($assignment->metadata ?? []),
            'segments' => $assignment->segments->map(fn ($segment): array => [
                'segment_order' => $segment->segment_order,
                'segment_type' => $segment->segment_type,
                'timing_mode' => $segment->timing_mode,
                'start_local_time' => $segment->start_local_time,
                'end_local_time' => $segment->end_local_time,
                'start_day_offset' => $segment->start_day_offset,
                'end_day_offset' => $segment->end_day_offset,
                'starts_at_utc' => $segment->starts_at_utc?->toJSON(),
                'ends_at_utc' => $segment->ends_at_utc?->toJSON(),
                'duration_minutes' => $segment->duration_minutes,
                'is_paid' => $segment->is_paid,
                'shift_template_segment_id' => $segment->shift_template_segment_id,
                'metadata' => $this->canonicalize($segment->metadata ?? []),
            ])->values()->all(),
        ];
    }

    private function difference(string $key, ?DailyScheduleAssignment $before, ?DailyScheduleAssignment $after, string $type, ?array $beforePayload = null, ?array $afterPayload = null): array
    {
        [$relationshipId, $workDate] = explode('|', $key);

        return [
            'type' => $type,
            'employment_relationship_id' => (int) $relationshipId,
            'worker_name' => $after?->employmentRelationship?->worker?->full_name ?? $before?->employmentRelationship?->worker?->full_name,
            'employee_code' => $after?->employmentRelationship?->worker?->employee_code ?? $before?->employmentRelationship?->worker?->employee_code,
            'work_date' => $workDate,
            'before_summary' => $before ? $this->summary($before) : null,
            'after_summary' => $after ? $this->summary($after) : null,
            'before' => $beforePayload,
            'after' => $afterPayload,
        ];
    }

    private function summary(DailyScheduleAssignment $assignment): string
    {
        return match ($assignment->day_type) {
            'shift' => 'Turno '.($assignment->shiftTemplate?->name ?? $assignment->shiftTemplate?->code ?? 'sin plantilla'),
            'rest' => 'Descanso',
            'flexible' => 'Flexible '.($assignment->required_minutes ?? 0).' min',
            'on_call' => 'Guardia bajo llamada',
            'unassigned' => 'Pendiente',
            default => $assignment->day_type,
        };
    }

    private function key(DailyScheduleAssignment $assignment): string
    {
        return $assignment->employment_relationship_id.'|'.$assignment->work_date->toDateString();
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
