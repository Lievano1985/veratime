<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\DailyScheduleAssignment;
use App\Models\ScheduleBatch;

class BuildScheduleBatchSnapshotAction
{
    public const SCHEMA_VERSION = 'f1.v1';

    public function handle(ScheduleBatch $batch): array
    {
        $batch = ScheduleBatch::query()
            ->with([
                'company',
                'center',
                'dailyAssignments' => fn ($query) => $query
                    ->with([
                        'employmentRelationship.worker',
                        'employmentRelationship.center',
                        'organizationalUnit',
                        'shiftTemplate',
                        'segments.shiftTemplateSegment',
                    ])
                    ->orderBy('work_date')
                    ->orderBy('employment_relationship_id')
                    ->orderBy('id'),
            ])
            ->findOrFail($batch->id);

        $payload = [
            'schema_version' => self::SCHEMA_VERSION,
            'batch' => [
                'id' => $batch->id,
                'company_id' => $batch->company_id,
                'company' => [
                    'name' => $batch->company?->name,
                    'legal_name' => $batch->company?->legal_name,
                    'tax_id' => $batch->company?->tax_id,
                ],
                'center_id' => $batch->center_id,
                'center' => [
                    'code' => $batch->center?->code,
                    'name' => $batch->center?->name,
                    'timezone' => $batch->center?->timezone,
                ],
                'period_start' => $batch->period_start->toDateString(),
                'period_end' => $batch->period_end->toDateString(),
                'version' => $batch->version,
                'creation_source' => $batch->creation_source,
            ],
            'assignments' => $batch->dailyAssignments
                ->map(fn (DailyScheduleAssignment $assignment): array => $this->assignmentPayload($assignment))
                ->values()
                ->all(),
        ];

        $canonicalPayload = $this->canonicalize($payload);
        $canonicalJson = json_encode($canonicalPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'payload' => $canonicalPayload,
            'canonical_json' => $canonicalJson,
            'sha256' => hash('sha256', (string) $canonicalJson),
        ];
    }

    private function assignmentPayload(DailyScheduleAssignment $assignment): array
    {
        $relationship = $assignment->employmentRelationship;
        $worker = $relationship?->worker;
        $unit = $assignment->organizationalUnit;
        $template = $assignment->shiftTemplate;

        return [
            'id' => $assignment->id,
            'employment_relationship_id' => $assignment->employment_relationship_id,
            'worker_id' => $worker?->id,
            'employee_code' => $worker?->employee_code,
            'worker_name' => $worker?->full_name,
            'work_date' => $assignment->work_date->toDateString(),
            'organizational_unit_id' => $assignment->organizational_unit_id,
            'organizational_unit' => $unit ? [
                'code' => $unit->code,
                'name' => $unit->name,
            ] : null,
            'day_type' => $assignment->day_type,
            'timezone' => $assignment->timezone,
            'shift_template_id' => $assignment->shift_template_id,
            'shift_template' => $template ? [
                'code' => $template->code,
                'name' => $template->name,
            ] : null,
            'source_type' => $assignment->source_type,
            'source_reference' => $assignment->source_reference,
            'required_minutes' => $assignment->required_minutes,
            'flexible_window' => [
                'start_local_time' => $assignment->window_start_local_time,
                'end_local_time' => $assignment->window_end_local_time,
                'start_day_offset' => $assignment->window_start_day_offset,
                'end_day_offset' => $assignment->window_end_day_offset,
            ],
            'on_call_availability' => [
                'start_local_time' => $assignment->availability_start_local_time,
                'end_local_time' => $assignment->availability_end_local_time,
                'start_day_offset' => $assignment->availability_start_day_offset,
                'end_day_offset' => $assignment->availability_end_day_offset,
            ],
            'max_work_minutes' => $assignment->max_work_minutes,
            'metadata' => $assignment->metadata,
            'segments' => $assignment->segments
                ->sortBy('segment_order')
                ->values()
                ->map(fn ($segment): array => [
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
                    'metadata' => $segment->metadata,
                ])
                ->all(),
        ];
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
