<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Organization\Actions\ResolveEmploymentUnitsForDateAction;
use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Domains\Scheduling\Data\GenerateDraftScheduleBatchFromProfilesResult;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\ScheduleBatch;
use App\Models\User;
use App\Support\RoleKey;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerateDraftScheduleBatchFromProfilesAction
{
    public const MODE_MISSING_ONLY = 'missing_only';
    public const MODE_REFRESH_PROFILE_GENERATED = 'refresh_profile_generated';
    private const GENERATOR = 'schedule_profile_generation';

    public function __construct(
        private ValidateScheduleBatchAction $batchValidator,
        private ResolveEmploymentUnitsForDateAction $resolveEmploymentUnits,
        private ResolveScheduleProfileForRelationshipAction $resolveProfile,
        private BuildDraftDailyScheduleFromResolvedProfileAction $buildDraftDay,
        private ReplaceDraftDailyScheduleAssignmentAction $replaceDraftDay,
        private ResolveScheduleBatchExpectedRelationshipDatesAction $expectedRelationshipDates,
    ) {
    }

    public function handle(
        User $actor,
        Company $company,
        ScheduleBatch $batch,
        string $regenerationMode = self::MODE_MISSING_ONLY,
    ): GenerateDraftScheduleBatchFromProfilesResult {
        if (! in_array($regenerationMode, [self::MODE_MISSING_ONLY, self::MODE_REFRESH_PROFILE_GENERATED], true)) {
            throw new InvalidArgumentException('El modo de regeneracion no es valido.');
        }

        $this->authorize($actor, $company, $batch);

        return DB::transaction(function () use ($actor, $company, $batch, $regenerationMode): GenerateDraftScheduleBatchFromProfilesResult {
            $lockedBatch = ScheduleBatch::query()
                ->with('center')
                ->lockForUpdate()
                ->findOrFail($batch->id);

            $this->authorize($actor, $company, $lockedBatch);
            $this->batchValidator->assertDraft($lockedBatch);
            $this->assertTimezone((string) $lockedBatch->center->timezone);

            $relationshipDates = $this->expectedRelationshipDates->handle($company, $lockedBatch, true);
            $existing = $this->existingAssignmentsForBatch($lockedBatch);
            $result = new GenerateDraftScheduleBatchFromProfilesResult($lockedBatch, $regenerationMode);
            $result->relationshipsConsidered = $relationshipDates->count();

            foreach ($relationshipDates as $item) {
                $relationship = $item['relationship'];
                $generatedForRelationship = false;
                foreach ($item['dates'] as $workDate) {
                    $result->datesConsidered++;
                    $key = $this->assignmentKey($relationship->id, $workDate);
                    $current = $existing[$key] ?? null;

                    if ($regenerationMode === self::MODE_MISSING_ONLY && $current) {
                        $result->assignmentsPreserved++;
                        continue;
                    }

                    if ($regenerationMode === self::MODE_REFRESH_PROFILE_GENERATED && $current && ! $this->isGeneratedByProfiles($current)) {
                        $result->assignmentsPreserved++;
                        continue;
                    }

                    $profileResolution = $this->resolveProfile->handle($company, $relationship, $workDate);
                    $unit = $this->primaryUnit($company, $relationship, $workDate, $lockedBatch);
                    $draft = $this->buildDraftDay->handle(
                        $company,
                        $relationship,
                        $workDate,
                        (string) $lockedBatch->center->timezone,
                        $unit,
                        $profileResolution,
                    );

                    $saved = $this->replaceDraftDay->handle($company, $lockedBatch, $relationship, $draft['data'], $draft['segments']);
                    $existing[$key] = $saved;
                    $generatedForRelationship = true;

                    if ($current) {
                        $result->assignmentsRefreshed++;
                    } else {
                        $result->assignmentsCreated++;
                    }

                    $result->countDayType($saved->day_type);
                }

                if (! $generatedForRelationship) {
                    $result->relationshipsSkipped++;
                }
            }

            if (($result->assignmentsCreated + $result->assignmentsRefreshed) > 0
                && ! in_array($lockedBatch->creation_source, ['profile', 'mixed'], true)) {
                $lockedBatch->forceFill(['creation_source' => 'mixed'])->save();
            }

            $result->scheduleBatch = $lockedBatch->refresh();

            return $result;
        });
    }

    private function authorize(User $actor, Company $company, ScheduleBatch $batch): void
    {
        if ($company->status !== 'active'
            || $actor->status !== 'active'
            || $batch->company_id !== $company->id
            || $batch->center?->company_id !== $company->id
            || ! $actor->belongsToCompany($company)) {
            throw new InvalidArgumentException('El usuario no puede generar programacion diaria para este lote.');
        }

        $canGenerate = in_array($actor->roleKeyForCompany($company), RoleKey::companyManagers(), true)
            || app(ScopedOperationalAccess::class)->canOperateFullCenter($actor, $company, $batch->center);

        if (! $canGenerate) {
            throw new InvalidArgumentException('El usuario no puede generar programacion diaria para este lote.');
        }

        if ($batch->previous_batch_id !== null) {
            throw new InvalidArgumentException('Una correccion versionada no puede regenerarse desde perfiles.');
        }
    }

    /**
     * @return array<string, DailyScheduleAssignment>
     */
    private function existingAssignmentsForBatch(ScheduleBatch $batch): array
    {
        return DailyScheduleAssignment::query()
            ->where('schedule_batch_id', $batch->id)
            ->whereDate('work_date', '>=', $batch->period_start->toDateString())
            ->whereDate('work_date', '<=', $batch->period_end->toDateString())
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (DailyScheduleAssignment $assignment): string => $this->assignmentKey(
                $assignment->employment_relationship_id,
                $assignment->work_date->toDateString(),
            ))
            ->all();
    }

    private function primaryUnit(Company $company, \App\Models\EmploymentRelationship $relationship, string $workDate, ScheduleBatch $batch): ?\App\Models\OrganizationalUnit
    {
        $unit = $this->resolveEmploymentUnits->handle($company, $relationship, $workDate)['primary'];
        if (! $unit) {
            return null;
        }

        if ($unit->company_id !== $company->id || $unit->center_id !== $batch->center_id) {
            throw new InvalidArgumentException('La unidad principal no pertenece al centro del lote.');
        }

        return $unit;
    }

    private function isGeneratedByProfiles(DailyScheduleAssignment $assignment): bool
    {
        $reference = $assignment->source_reference ?? [];

        return in_array($assignment->source_type, ['profile', 'system'], true)
            && is_array($reference)
            && ($reference['generator'] ?? null) === self::GENERATOR;
    }

    private function assignmentKey(int $relationshipId, string $workDate): string
    {
        return $relationshipId.'|'.$workDate;
    }

    private function assertTimezone(string $timezone): void
    {
        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('La zona horaria del centro no es valida.');
        }
    }
}
