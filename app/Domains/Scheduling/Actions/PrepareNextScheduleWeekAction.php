<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\GenerateDraftScheduleBatchFromProfilesResult;
use App\Models\Company;
use App\Models\ScheduleBatch;
use App\Models\User;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class PrepareNextScheduleWeekAction
{
    public function __construct(
        private CreateScheduleBatchAction $createBatch,
        private GenerateDraftScheduleBatchFromProfilesAction $generateFromProfiles,
        private ValidateScheduleBatchAction $batchValidator,
    ) {
    }

    /**
     * @return array{batch: ScheduleBatch, created: bool, generation_result: ?GenerateDraftScheduleBatchFromProfilesResult}
     */
    public function handle(User $actor, Company $company, ScheduleBatch $currentBatch): array
    {
        $currentBatch->loadMissing('center');
        $this->authorize($actor, $company, $currentBatch);

        if ($currentBatch->previous_batch_id !== null) {
            throw new InvalidArgumentException('Una correccion versionada no puede preparar la siguiente semana.');
        }

        [$nextStart, $nextEnd] = $this->nextNaturalWeek($currentBatch);
        $existing = $this->existingActiveBatch($company, $currentBatch, $nextStart, $nextEnd);

        if ($existing) {
            return [
                'batch' => $existing,
                'created' => false,
                'generation_result' => null,
            ];
        }

        $batch = $this->createBatch->handle($company, $currentBatch->center, [
            'period_start' => $nextStart,
            'period_end' => $nextEnd,
            'creation_source' => 'profile',
            'notes' => 'Semana preparada desde la programacion anterior.',
        ], $actor);

        $generationResult = $this->generateFromProfiles->handle(
            $actor,
            $company,
            $batch,
            GenerateDraftScheduleBatchFromProfilesAction::MODE_MISSING_ONLY,
        );

        return [
            'batch' => $batch->refresh(),
            'created' => true,
            'generation_result' => $generationResult,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function nextNaturalWeek(ScheduleBatch $currentBatch): array
    {
        $nextDate = CarbonImmutable::parse($currentBatch->period_end)->addDay()->toDateString();

        return $this->batchValidator->naturalWeekForDate($nextDate);
    }

    private function existingActiveBatch(Company $company, ScheduleBatch $currentBatch, string $nextStart, string $nextEnd): ?ScheduleBatch
    {
        return ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('center_id', $currentBatch->center_id)
            ->whereDate('period_start', $nextStart)
            ->whereDate('period_end', $nextEnd)
            ->whereNull('previous_batch_id')
            ->whereIn('status', ['draft', 'published', 'superseded'])
            ->orderByRaw("case when status = 'draft' then 0 when status = 'published' then 1 else 2 end")
            ->orderByDesc('version')
            ->first();
    }

    private function authorize(User $actor, Company $company, ScheduleBatch $currentBatch): void
    {
        if ($company->status !== 'active'
            || $actor->status !== 'active'
            || $currentBatch->company_id !== $company->id
            || $currentBatch->center?->company_id !== $company->id
            || ! $actor->belongsToCompany($company)
            || ! in_array($actor->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            throw new InvalidArgumentException('El usuario no puede preparar la siguiente semana para este lote.');
        }
    }
}
