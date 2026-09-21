<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DailyScheduleAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DailyScheduleAssignment */
class PersonalScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'work_date' => $this->work_date?->toDateString(),
            'day_type' => $this->day_type,
            'timezone' => $this->timezone,
            'required_minutes' => $this->required_minutes,
            'center' => $this->employmentRelationship?->center ? [
                'id' => (string) $this->employmentRelationship->center->id,
                'name' => $this->employmentRelationship->center->name,
            ] : null,
            'shift_template' => $this->shiftTemplate ? [
                'id' => (string) $this->shiftTemplate->id,
                'name' => $this->shiftTemplate->name,
            ] : null,
            'segments' => $this->segments->map(fn ($segment): array => [
                'order' => $segment->segment_order,
                'type' => $segment->segment_type,
                'timing_mode' => $segment->timing_mode,
                'start_local_time' => $segment->start_local_time,
                'end_local_time' => $segment->end_local_time,
                'start_day_offset' => $segment->start_day_offset,
                'end_day_offset' => $segment->end_day_offset,
                'duration_minutes' => $segment->duration_minutes,
                'is_paid' => $segment->is_paid,
            ])->values()->all(),
        ];
    }
}
