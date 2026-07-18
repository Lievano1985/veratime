<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use Carbon\CarbonImmutable;
use LogicException;

class ResolveDailyScheduleForRelationshipDateAction
{
    public function handle(Company $company, EmploymentRelationship $relationship, string $workDate): array
    {
        $date = CarbonImmutable::parse($workDate)->toDateString();

        if ($company->status !== 'active' || $relationship->company_id !== $company->id) {
            throw new \InvalidArgumentException('La relacion laboral no corresponde a la empresa activa.');
        }

        $assignments = DailyScheduleAssignment::query()
            ->with([
                'scheduleBatch.center',
                'employmentRelationship.worker',
                'employmentRelationship.center',
                'organizationalUnit',
                'shiftTemplate',
                'segments.shiftTemplateSegment',
            ])
            ->where('company_id', $company->id)
            ->where('employment_relationship_id', $relationship->id)
            ->whereDate('work_date', $date)
            ->whereHas('scheduleBatch', fn ($query) => $query->where('status', 'published'))
            ->orderBy('id')
            ->get();

        if ($assignments->count() > 1) {
            throw new LogicException('Existe mas de una programacion publicada para la misma relacion laboral y fecha.');
        }

        if ($assignments->isEmpty()) {
            return [
                'resolution_status' => 'missing',
                'schedule_batch' => null,
                'batch_version' => null,
                'snapshot_sha256' => null,
                'daily_schedule_assignment' => null,
                'segments' => collect(),
                'relationship' => $relationship,
                'worker' => $relationship->worker,
                'center' => $relationship->center,
                'organizational_unit' => null,
                'work_date' => $date,
                'timezone' => null,
                'day_type' => null,
                'shift_template' => null,
                'source_type' => null,
                'source_reference' => null,
                'required_minutes' => null,
                'flexible_window' => null,
                'on_call_availability' => null,
                'max_work_minutes' => null,
            ];
        }

        $assignment = $assignments->first();
        $batch = $assignment->scheduleBatch;

        return [
            'resolution_status' => 'published',
            'schedule_batch' => $batch,
            'batch_version' => $batch->version,
            'snapshot_sha256' => $batch->snapshot_sha256,
            'daily_schedule_assignment' => $assignment,
            'segments' => $assignment->segments,
            'relationship' => $assignment->employmentRelationship,
            'worker' => $assignment->employmentRelationship->worker,
            'center' => $assignment->employmentRelationship->center,
            'organizational_unit' => $assignment->organizationalUnit,
            'work_date' => $assignment->work_date->toDateString(),
            'timezone' => $assignment->timezone,
            'day_type' => $assignment->day_type,
            'shift_template' => $assignment->shiftTemplate,
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
        ];
    }
}
