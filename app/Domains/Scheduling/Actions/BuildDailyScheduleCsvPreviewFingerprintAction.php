<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\DailyScheduleAssignment;
use App\Models\ImportBatch;
use App\Models\ImportRow;

class BuildDailyScheduleCsvPreviewFingerprintAction
{
    public function handle(ImportBatch $importBatch): string
    {
        $rows = $importBatch->rows()
            ->whereIn('status', ['valid', 'warning'])
            ->orderBy('row_number')
            ->get();

        $payload = $rows->map(function (ImportRow $row): array {
            return [
                'row' => $row->row_number,
                'fingerprint' => $row->row_fingerprint,
                'existing' => $this->assignmentFingerprint($row->existingDailyScheduleAssignment),
            ];
        })->all();

        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function assignmentFingerprint(?DailyScheduleAssignment $assignment): ?string
    {
        if (! $assignment) {
            return null;
        }

        $assignment->loadMissing('segments');

        return hash('sha256', json_encode([
            'work_date' => $assignment->work_date->toDateString(),
            'day_type' => $assignment->day_type,
            'timezone' => $assignment->timezone,
            'shift_template_id' => $assignment->shift_template_id,
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
            'segments' => $assignment->segments->map(fn ($segment): array => [
                'segment_order' => $segment->segment_order,
                'segment_type' => $segment->segment_type,
                'timing_mode' => $segment->timing_mode,
                'start_local_time' => $segment->start_local_time,
                'end_local_time' => $segment->end_local_time,
                'start_day_offset' => $segment->start_day_offset,
                'end_day_offset' => $segment->end_day_offset,
                'duration_minutes' => $segment->duration_minutes,
                'is_paid' => $segment->is_paid,
                'shift_template_segment_id' => $segment->shift_template_segment_id,
            ])->values()->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<string, mixed> $normalizedData
     */
    public function rowFingerprint(array $normalizedData): string
    {
        $assignment = $normalizedData['assignment'] ?? [];

        return hash('sha256', json_encode([
            'work_date' => $assignment['work_date'] ?? null,
            'day_type' => $assignment['day_type'] ?? null,
            'timezone' => $assignment['timezone'] ?? null,
            'shift_template_id' => $assignment['shift_template_id'] ?? null,
            'required_minutes' => $assignment['required_minutes'] ?? null,
            'window_start_local_time' => $assignment['window_start_local_time'] ?? null,
            'window_end_local_time' => $assignment['window_end_local_time'] ?? null,
            'window_start_day_offset' => $assignment['window_start_day_offset'] ?? 0,
            'window_end_day_offset' => $assignment['window_end_day_offset'] ?? 0,
            'availability_start_local_time' => $assignment['availability_start_local_time'] ?? null,
            'availability_end_local_time' => $assignment['availability_end_local_time'] ?? null,
            'availability_start_day_offset' => $assignment['availability_start_day_offset'] ?? 0,
            'availability_end_day_offset' => $assignment['availability_end_day_offset'] ?? 0,
            'max_work_minutes' => $assignment['max_work_minutes'] ?? null,
            'segments' => collect($normalizedData['segments'] ?? [])->map(fn (array $segment): array => [
                'segment_order' => $segment['segment_order'] ?? null,
                'segment_type' => $segment['segment_type'] ?? null,
                'timing_mode' => $segment['timing_mode'] ?? null,
                'start_local_time' => $segment['start_local_time'] ?? null,
                'end_local_time' => $segment['end_local_time'] ?? null,
                'start_day_offset' => $segment['start_day_offset'] ?? 0,
                'end_day_offset' => $segment['end_day_offset'] ?? 0,
                'duration_minutes' => $segment['duration_minutes'] ?? null,
                'is_paid' => $segment['is_paid'] ?? false,
                'shift_template_segment_id' => $segment['shift_template_segment_id'] ?? null,
            ])->values()->all(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
