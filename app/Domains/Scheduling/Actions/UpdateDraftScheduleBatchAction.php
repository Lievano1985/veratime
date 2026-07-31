<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\ScheduleBatch;
use Illuminate\Support\Facades\DB;

class UpdateDraftScheduleBatchAction
{
    public function __construct(private ValidateScheduleBatchAction $validator)
    {
    }

    public function handle(ScheduleBatch $batch, array $data): ScheduleBatch
    {
        return DB::transaction(function () use ($batch, $data): ScheduleBatch {
            $lockedBatch = ScheduleBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $this->validator->assertDraft($lockedBatch);

            [$periodStart, $periodEnd] = $this->validator->naturalWeekForDate(
                (string) ($data['period_start'] ?? $lockedBatch->period_start->toDateString()),
            );
            [$periodStart, $periodEnd] = $this->validator->validatePeriod($periodStart, $periodEnd);

            $outsideNewPeriod = $lockedBatch->dailyAssignments()
                ->where(function ($query) use ($periodStart, $periodEnd): void {
                    $query->whereDate('work_date', '<', $periodStart)
                        ->orWhereDate('work_date', '>', $periodEnd);
                })
                ->exists();

            if ($outsideNewPeriod) {
                throw new \InvalidArgumentException('No se puede mover el periodo porque existen dias fuera del nuevo rango.');
            }

            $lockedBatch->forceFill([
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'creation_source' => isset($data['creation_source'])
                    ? $this->validator->validateCreationSource((string) $data['creation_source'])
                    : $lockedBatch->creation_source,
                'notes' => array_key_exists('notes', $data) ? (blank($data['notes']) ? null : trim((string) $data['notes'])) : $lockedBatch->notes,
            ])->save();

            return $lockedBatch->refresh();
        });
    }
}
