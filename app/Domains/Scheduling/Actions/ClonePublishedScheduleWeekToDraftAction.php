<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use App\Models\User;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ClonePublishedScheduleWeekToDraftAction
{
    public function __construct(private ValidateScheduleBatchAction $batchValidator)
    {
    }

    /**
     * @return array{batch: ScheduleBatch, assignments: int, segments: int, skipped: int}
     */
    public function handle(User $actor, Company $company, ScheduleBatch $published, string $targetDate): array
    {
        return DB::transaction(function () use ($actor, $company, $published, $targetDate): array {
            $published = ScheduleBatch::query()
                ->with(['center', 'dailyAssignments.segments', 'dailyAssignments.employmentRelationship.worker'])
                ->lockForUpdate()
                ->findOrFail($published->id);

            $this->authorize($actor, $company, $published);
            [$targetStart, $targetEnd] = $this->batchValidator->naturalWeekForDate($targetDate);
            $this->ensureTargetIsAvailable($company, $published, $targetStart, $targetEnd);

            $dayOffset = (int) CarbonImmutable::parse($published->period_start)->diffInDays(CarbonImmutable::parse($targetStart), false);

            $draft = new ScheduleBatch([
                'period_start' => $targetStart,
                'period_end' => $targetEnd,
                'version' => null,
                'status' => 'draft',
                'creation_source' => 'mixed',
                'notes' => 'Semana clonada desde publicacion '.$published->period_start->toDateString().' a '.$published->period_end->toDateString().'.',
            ]);
            $draft->company()->associate($company);
            $draft->center()->associate($published->center_id);
            $draft->creator()->associate($actor);
            $draft->save();

            $assignments = 0;
            $segments = 0;
            $skipped = 0;

            foreach ($published->dailyAssignments as $assignment) {
                $targetWorkDate = CarbonImmutable::parse($assignment->work_date)->addDays($dayOffset)->toDateString();
                $relationship = $assignment->employmentRelationship;

                if (! $relationship
                    || $relationship->company_id !== $company->id
                    || $relationship->center_id !== $published->center_id
                    || ! $relationship->isEffectiveOn($targetWorkDate)
                    || $relationship->worker?->status !== 'active') {
                    $skipped++;
                    continue;
                }

                $copy = $this->copyAssignment($company, $draft, $relationship, $assignment, $targetWorkDate, $published, $dayOffset);
                $assignments++;

                foreach ($assignment->segments as $segment) {
                    $this->copySegment($company, $copy, $segment, $dayOffset);
                    $segments++;
                }
            }

            if ($assignments === 0) {
                throw new InvalidArgumentException('No se pudo clonar la semana porque no hay trabajadores vigentes en el periodo destino.');
            }

            return [
                'batch' => $draft->refresh(),
                'assignments' => $assignments,
                'segments' => $segments,
                'skipped' => $skipped,
            ];
        });
    }

    private function authorize(User $actor, Company $company, ScheduleBatch $published): void
    {
        if ($company->status !== 'active'
            || $actor->status !== 'active'
            || $published->company_id !== $company->id
            || $published->center?->company_id !== $company->id
            || $published->center?->status !== 'active'
            || $published->status !== 'published'
            || $published->previous_batch_id !== null
            || ! $actor->belongsToCompany($company)
            || ! in_array($actor->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            throw new InvalidArgumentException('Solo se puede clonar una semana publicada vigente de la empresa activa.');
        }
    }

    private function ensureTargetIsAvailable(Company $company, ScheduleBatch $published, string $targetStart, string $targetEnd): void
    {
        $exists = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('center_id', $published->center_id)
            ->whereNull('previous_batch_id')
            ->whereDate('period_start', $targetStart)
            ->whereDate('period_end', $targetEnd)
            ->whereIn('status', ['draft', 'published', 'superseded'])
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('Ya existe una semana activa para el centro y periodo destino.');
        }
    }

    private function copyAssignment(
        Company $company,
        ScheduleBatch $draft,
        EmploymentRelationship $relationship,
        DailyScheduleAssignment $assignment,
        string $targetWorkDate,
        ScheduleBatch $published,
        int $dayOffset,
    ): DailyScheduleAssignment {
        $copy = new DailyScheduleAssignment([
            'work_date' => $targetWorkDate,
            'day_type' => $assignment->day_type,
            'timezone' => $assignment->timezone,
            'source_type' => 'manual',
            'source_reference' => [
                'schema_version' => 1,
                'reason' => 'cloned_from_published_week',
                'source_batch_id' => $published->id,
                'source_work_date' => $assignment->work_date->toDateString(),
                'day_offset' => $dayOffset,
                'previous_source_type' => $assignment->source_type,
            ],
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
        $copy->employmentRelationship()->associate($relationship);
        $copy->organizationalUnit()->associate($assignment->organizational_unit_id);
        $copy->shiftTemplate()->associate($assignment->shift_template_id);
        $copy->save();

        return $copy;
    }

    private function copySegment(Company $company, DailyScheduleAssignment $copy, DailyScheduleSegment $segment, int $dayOffset): void
    {
        $segmentCopy = new DailyScheduleSegment([
            'segment_order' => $segment->segment_order,
            'segment_type' => $segment->segment_type,
            'timing_mode' => $segment->timing_mode,
            'start_local_time' => $segment->start_local_time,
            'end_local_time' => $segment->end_local_time,
            'start_day_offset' => $segment->start_day_offset,
            'end_day_offset' => $segment->end_day_offset,
            'starts_at_utc' => $segment->starts_at_utc?->copy()->addDays($dayOffset),
            'ends_at_utc' => $segment->ends_at_utc?->copy()->addDays($dayOffset),
            'duration_minutes' => $segment->duration_minutes,
            'is_paid' => $segment->is_paid,
            'metadata' => $segment->metadata,
        ]);
        $segmentCopy->company()->associate($company);
        $segmentCopy->dailyScheduleAssignment()->associate($copy);
        $segmentCopy->shiftTemplateSegment()->associate($segment->shift_template_segment_id);
        $segmentCopy->save();
    }
}
