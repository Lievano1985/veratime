<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class ResolveScheduleBatchExpectedRelationshipDatesAction
{
    /**
     * @return Collection<int, array{relationship: EmploymentRelationship, dates: list<string>}>
     */
    public function handle(Company $company, ScheduleBatch $batch, bool $lockForUpdate = false): Collection
    {
        $query = EmploymentRelationship::query()
            ->with(['worker', 'center'])
            ->where('company_id', $company->id)
            ->where('center_id', $batch->center_id)
            ->where('status', 'active')
            ->whereDate('started_at', '<=', $batch->period_end->toDateString())
            ->where(function ($query) use ($batch): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $batch->period_start->toDateString());
            })
            ->whereHas('worker', fn ($query) => $query->where('company_id', $company->id)->where('status', 'active'))
            ->orderBy('worker_id')
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get()
            ->map(fn (EmploymentRelationship $relationship): array => [
                'relationship' => $relationship,
                'dates' => $this->datesForRelationship($batch, $relationship),
            ])
            ->filter(fn (array $item): bool => $item['dates'] !== [])
            ->values();
    }

    /**
     * @return list<string>
     */
    private function datesForRelationship(ScheduleBatch $batch, EmploymentRelationship $relationship): array
    {
        $start = CarbonImmutable::parse(max(
            $batch->period_start->toDateString(),
            $relationship->started_at->toDateString(),
        ));
        $end = CarbonImmutable::parse($relationship->ended_at
            ? min($batch->period_end->toDateString(), $relationship->ended_at->toDateString())
            : $batch->period_end->toDateString());

        if ($end->lt($start)) {
            return [];
        }

        return collect(CarbonPeriod::create($start, $end))
            ->map(fn ($date): string => $date->toDateString())
            ->values()
            ->all();
    }
}
