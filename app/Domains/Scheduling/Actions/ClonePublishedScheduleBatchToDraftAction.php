<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\ScheduleBatch;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClonePublishedScheduleBatchToDraftAction
{
    /**
     * @return array{assignments: int, segments: int}
     */
    public function handle(Company $company, ScheduleBatch $published, ScheduleBatch $draft): array
    {
        return DB::transaction(function () use ($company, $published, $draft): array {
            $published = ScheduleBatch::query()
                ->with('dailyAssignments.segments')
                ->lockForUpdate()
                ->findOrFail($published->id);
            $draft = ScheduleBatch::query()->lockForUpdate()->findOrFail($draft->id);

            if ($published->company_id !== $company->id || $draft->company_id !== $company->id) {
                throw new InvalidArgumentException('La clonacion no corresponde a la empresa activa.');
            }

            if ($published->status !== 'published' || $draft->status !== 'draft' || $draft->previous_batch_id !== $published->id) {
                throw new InvalidArgumentException('La clonacion requiere una publicacion origen y una correccion en borrador.');
            }

            if ($draft->dailyAssignments()->exists()) {
                throw new InvalidArgumentException('La correccion en borrador ya contiene programacion.');
            }

            $assignments = 0;
            $segments = 0;

            foreach ($published->dailyAssignments as $assignment) {
                $copy = new DailyScheduleAssignment([
                    'work_date' => $assignment->work_date->toDateString(),
                    'day_type' => $assignment->day_type,
                    'timezone' => $assignment->timezone,
                    'source_type' => $assignment->source_type,
                    'source_reference' => $assignment->source_reference,
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
                    'metadata' => $assignment->metadata,
                ]);
                $copy->company()->associate($company);
                $copy->scheduleBatch()->associate($draft);
                $copy->employmentRelationship()->associate($assignment->employment_relationship_id);
                $copy->organizationalUnit()->associate($assignment->organizational_unit_id);
                $copy->shiftTemplate()->associate($assignment->shift_template_id);
                $copy->save();
                $assignments++;

                foreach ($assignment->segments as $segment) {
                    $segmentCopy = new DailyScheduleSegment([
                        'segment_order' => $segment->segment_order,
                        'segment_type' => $segment->segment_type,
                        'timing_mode' => $segment->timing_mode,
                        'start_local_time' => $segment->start_local_time,
                        'end_local_time' => $segment->end_local_time,
                        'start_day_offset' => $segment->start_day_offset,
                        'end_day_offset' => $segment->end_day_offset,
                        'starts_at_utc' => $segment->starts_at_utc,
                        'ends_at_utc' => $segment->ends_at_utc,
                        'duration_minutes' => $segment->duration_minutes,
                        'is_paid' => $segment->is_paid,
                        'metadata' => $segment->metadata,
                    ]);
                    $segmentCopy->company()->associate($company);
                    $segmentCopy->dailyScheduleAssignment()->associate($copy);
                    $segmentCopy->shiftTemplateSegment()->associate($segment->shift_template_segment_id);
                    $segmentCopy->save();
                    $segments++;
                }
            }

            return ['assignments' => $assignments, 'segments' => $segments];
        });
    }
}
