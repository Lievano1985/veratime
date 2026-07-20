<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\PublishCorrectiveScheduleBatchResult;
use App\Domains\Scheduling\Exceptions\ScheduleCorrectionHasNoChangesException;
use App\Domains\Scheduling\Exceptions\ScheduleCorrectionPublicationConflictException;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PublishCorrectiveScheduleBatchAction
{
    public function __construct(
        private ValidateCorrectiveScheduleBatchForPublicationAction $validator,
        private CompareScheduleBatchVersionsAction $compareVersions,
        private BuildScheduleBatchSnapshotAction $snapshotBuilder,
    ) {
    }

    public function handle(User $actor, Company $company, ScheduleBatch $corrective): PublishCorrectiveScheduleBatchResult
    {
        return DB::transaction(function () use ($actor, $company, $corrective): PublishCorrectiveScheduleBatchResult {
            $corrective = ScheduleBatch::query()->with(['previousBatch', 'dailyAssignments.segments'])->lockForUpdate()->findOrFail($corrective->id);
            $previous = ScheduleBatch::query()->with('dailyAssignments.segments')->lockForUpdate()->findOrFail($corrective->previous_batch_id);
            Center::query()->whereKey($corrective->center_id)->lockForUpdate()->firstOrFail();

            DailyScheduleAssignment::query()
                ->whereIn('schedule_batch_id', [$previous->id, $corrective->id])
                ->lockForUpdate()
                ->get();

            DailyScheduleSegment::query()
                ->whereHas('dailyScheduleAssignment', fn ($query) => $query->whereIn('schedule_batch_id', [$previous->id, $corrective->id]))
                ->lockForUpdate()
                ->get();

            $validation = $this->validator->handle($actor, $company, $corrective);
            if (! $validation->valid()) {
                if ($validation->changedDays < 1) {
                    throw new ScheduleCorrectionHasNoChangesException();
                }

                throw new ScheduleCorrectionPublicationConflictException(implode(' ', $validation->errors));
            }

            $comparison = $this->compareVersions->handle($previous, $corrective);
            if ($comparison->changedDays < 1) {
                throw new ScheduleCorrectionHasNoChangesException();
            }

            $snapshot = $this->snapshotBuilder->handle($corrective);
            $this->assertSnapshot($snapshot);
            $publishedAt = now();

            $previous->forceFill([
                'status' => 'superseded',
                'superseded_by' => $corrective->id,
                'superseded_at' => $publishedAt,
            ])->save();

            $corrective->forceFill([
                'status' => 'published',
                'snapshot_schema_version' => $snapshot['schema_version'],
                'snapshot_canonical_json' => $snapshot['canonical_json'],
                'snapshot_sha256' => $snapshot['sha256'],
                'published_by' => $actor->id,
                'published_at' => $publishedAt,
            ])->save();

            $fresh = $corrective->refresh()->load('dailyAssignments.segments');

            return new PublishCorrectiveScheduleBatchResult(
                previousBatch: $previous->refresh(),
                correctiveBatch: $fresh,
                publishedAt: $publishedAt,
                snapshotSchemaVersion: $snapshot['schema_version'],
                snapshotSha256: $snapshot['sha256'],
                changedDays: $comparison->changedDays,
                assignmentCount: $fresh->dailyAssignments->count(),
                segmentCount: (int) $fresh->dailyAssignments->sum(fn ($assignment) => $assignment->segments->count()),
            );
        });
    }

    private function assertSnapshot(array $snapshot): void
    {
        $json = $snapshot['canonical_json'] ?? null;
        $hash = (string) ($snapshot['sha256'] ?? '');

        if (($snapshot['schema_version'] ?? null) !== BuildScheduleBatchSnapshotAction::SCHEMA_VERSION
            || ! is_string($json)
            || $json === ''
            || json_decode($json, true) === null
            || json_last_error() !== JSON_ERROR_NONE
            || ! preg_match('/^[a-f0-9]{64}$/', $hash)
            || ! hash_equals(hash('sha256', $json), $hash)) {
            throw new ScheduleCorrectionPublicationConflictException('El snapshot correctivo no es valido.');
        }
    }
}
