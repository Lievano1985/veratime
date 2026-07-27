<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\PublishScheduleBatchResult;
use App\Domains\Scheduling\Exceptions\PublishedScheduleConflictException;
use App\Domains\Scheduling\Exceptions\ScheduleBatchIntegrityException;
use App\Domains\Scheduling\Exceptions\ScheduleBatchPublicationValidationException;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PublishScheduleBatchAction
{
    public function __construct(
        private ValidateScheduleBatchForPublicationAction $validator,
        private BuildScheduleBatchSnapshotAction $snapshotBuilder,
    ) {
    }

    public function handle(User $actor, Company $company, ScheduleBatch $batch): PublishScheduleBatchResult
    {
        return DB::transaction(function () use ($actor, $company, $batch): PublishScheduleBatchResult {
            $lockedCompany = Company::query()->lockForUpdate()->findOrFail($company->id);
            $lockedBatch = ScheduleBatch::query()->with(['center', 'dailyAssignments.segments'])->lockForUpdate()->findOrFail($batch->id);
            Center::query()->whereKey($lockedBatch->center_id)->lockForUpdate()->firstOrFail();

            DailyScheduleAssignment::query()
                ->where('schedule_batch_id', $lockedBatch->id)
                ->lockForUpdate()
                ->get();

            DailyScheduleSegment::query()
                ->whereIn('daily_schedule_assignment_id', $lockedBatch->dailyAssignments->pluck('id')->all())
                ->lockForUpdate()
                ->get();

            ScheduleBatch::query()
                ->where('company_id', $lockedBatch->company_id)
                ->where('center_id', $lockedBatch->center_id)
                ->where('status', 'published')
                ->where('id', '!=', $lockedBatch->id)
                ->whereDate('period_start', '<=', $lockedBatch->period_end->toDateString())
                ->whereDate('period_end', '>=', $lockedBatch->period_start->toDateString())
                ->lockForUpdate()
                ->get();

            $validation = $this->validator->handle($actor, $lockedCompany, $lockedBatch);
            if (! $validation->valid()) {
                if ($validation->conflictingAssignments > 0) {
                    throw new PublishedScheduleConflictException($validation);
                }

                throw new ScheduleBatchPublicationValidationException($validation);
            }

            $lockedBatch->forceFill([
                'version' => 1,
            ])->save();

            $snapshot = $this->snapshotBuilder->handle($lockedBatch);
            $this->assertSnapshot($snapshot);

            $publishedAt = now();
            $lockedBatch->forceFill([
                'status' => 'published',
                'snapshot_schema_version' => $snapshot['schema_version'],
                'snapshot_canonical_json' => $snapshot['canonical_json'],
                'snapshot_sha256' => $snapshot['sha256'],
                'published_by' => $actor->id,
                'published_at' => $publishedAt,
            ])->save();

            $freshBatch = $lockedBatch->refresh()->load('dailyAssignments.segments');

            return new PublishScheduleBatchResult(
                scheduleBatch: $freshBatch,
                publishedAt: $publishedAt,
                publishedBy: $actor->id,
                snapshotSchemaVersion: $snapshot['schema_version'],
                snapshotSha256: $snapshot['sha256'],
                assignmentCount: $validation->assignmentsFound,
                segmentCount: (int) $freshBatch->dailyAssignments->sum(fn ($assignment) => $assignment->segments->count()),
                relationshipCount: $validation->relationshipsExpected,
                workDateCount: $validation->datesExpected,
                countsByDayType: [
                    'shift' => $validation->assignmentsShift,
                    'rest' => $validation->assignmentsRest,
                    'flexible' => $validation->assignmentsFlexible,
                    'on_call' => $validation->assignmentsOnCall,
                ],
                warnings: $validation->warnings,
            );
        });
    }

    /**
     * @param array{schema_version: string, canonical_json: string|null|false, sha256: string} $snapshot
     */
    private function assertSnapshot(array $snapshot): void
    {
        if (($snapshot['schema_version'] ?? null) !== BuildScheduleBatchSnapshotAction::SCHEMA_VERSION) {
            throw new ScheduleBatchIntegrityException('La version del snapshot no es compatible.');
        }

        $json = $snapshot['canonical_json'] ?? null;
        if (! is_string($json) || $json === '' || json_decode($json, true) === null || json_last_error() !== JSON_ERROR_NONE) {
            throw new ScheduleBatchIntegrityException('El snapshot canonico no es JSON valido.');
        }

        $hash = (string) ($snapshot['sha256'] ?? '');
        if (! preg_match('/^[a-f0-9]{64}$/', $hash) || ! hash_equals(hash('sha256', $json), $hash)) {
            throw new ScheduleBatchIntegrityException('El hash SHA-256 del snapshot no es valido.');
        }
    }
}
