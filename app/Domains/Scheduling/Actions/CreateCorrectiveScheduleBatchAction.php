<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\CreateCorrectiveScheduleBatchResult;
use App\Domains\Scheduling\Exceptions\InvalidScheduleCorrectionSourceException;
use App\Domains\Scheduling\Exceptions\ScheduleCorrectionAlreadyExistsException;
use App\Models\Center;
use App\Models\Company;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateCorrectiveScheduleBatchAction
{
    public function __construct(
        private ClonePublishedScheduleBatchToDraftAction $clone,
        private VerifyPublishedScheduleBatchSnapshotAction $verifySnapshot,
    ) {
    }

    public function handle(User $actor, Company $company, ScheduleBatch $published, string $correctionReason): CreateCorrectiveScheduleBatchResult
    {
        return DB::transaction(function () use ($actor, $company, $published, $correctionReason): CreateCorrectiveScheduleBatchResult {
            $reason = preg_replace('/\s+/', ' ', trim($correctionReason));
            if ($reason === '') {
                throw new InvalidScheduleCorrectionSourceException('El motivo general de correccion es obligatorio.');
            }

            $published = ScheduleBatch::query()->with(['company', 'center'])->lockForUpdate()->findOrFail($published->id);
            Center::query()->whereKey($published->center_id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('createCorrection', $published);

            if ($published->company_id !== $company->id || $published->status !== 'published' || $published->previous_batch_id !== null && $published->version < 2) {
                throw new InvalidScheduleCorrectionSourceException('Solo una version publicada vigente puede iniciar una correccion.');
            }

            $verification = $this->verifySnapshot->handle($company, $published);
            if (! $verification->valid) {
                throw new InvalidScheduleCorrectionSourceException('La publicacion origen no tiene evidencia integra.');
            }

            $existing = ScheduleBatch::query()
                ->where('company_id', $company->id)
                ->where('previous_batch_id', $published->id)
                ->whereIn('status', ['draft', 'published'])
                ->lockForUpdate()
                ->first();
            if ($existing) {
                throw new ScheduleCorrectionAlreadyExistsException($existing->id);
            }

            $draft = new ScheduleBatch([
                'period_start' => $published->period_start->toDateString(),
                'period_end' => $published->period_end->toDateString(),
                'version' => ((int) $published->version) + 1,
                'status' => 'draft',
                'creation_source' => 'mixed',
                'notes' => $published->notes,
                'correction_reason' => $reason,
            ]);
            $draft->company()->associate($company);
            $draft->center()->associate($published->center_id);
            $draft->previousBatch()->associate($published);
            $draft->creator()->associate($actor);
            $draft->save();

            $cloned = $this->clone->handle($company, $published, $draft);

            return new CreateCorrectiveScheduleBatchResult(
                previousBatch: $published->refresh(),
                correctiveBatch: $draft->refresh(),
                assignmentsCloned: $cloned['assignments'],
                segmentsCloned: $cloned['segments'],
            );
        });
    }
}
