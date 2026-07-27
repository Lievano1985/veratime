<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Organization\Actions\ResolveEmploymentUnitsForDateAction;
use App\Domains\Scheduling\Support\DailyScheduleCsvSchema;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerateDailyScheduleCsvTemplateAction
{
    public function __construct(
        private ResolveScheduleBatchExpectedRelationshipDatesAction $expectedDates,
        private ResolveEmploymentUnitsForDateAction $resolveUnits,
    ) {
    }

    /**
     * @param array{worker_search?: string|null, organizational_unit_id?: int|string|null} $filters
     */
    public function handle(Company $company, ?ScheduleBatch $batch = null, array $filters = []): StreamedResponse
    {
        $dates = $batch ? $this->batchDates($batch) : [];
        $rows = $batch ? $this->pendingRows($company, $batch, $filters, $dates) : [];
        $filename = $batch
            ? 'vera-time-programacion-diaria-pendientes-v1.csv'
            : 'vera-time-programacion-diaria-v1.csv';

        return response()->streamDownload(function () use ($batch, $dates, $rows): void {
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'wb');
            fputcsv($output, $batch ? DailyScheduleCsvSchema::horizontalHeaders($dates) : DailyScheduleCsvSchema::headers());

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /**
     * @param array{worker_search?: string|null, organizational_unit_id?: int|string|null} $filters
     * @param list<string> $dates
     * @return list<list<string>>
     */
    private function pendingRows(Company $company, ScheduleBatch $batch, array $filters, array $dates): array
    {
        $batch->loadMissing([
            'dailyAssignments.employmentRelationship.worker',
            'dailyAssignments.employmentRelationship.center',
            'dailyAssignments.organizationalUnit',
        ]);

        $search = mb_strtolower(trim((string) ($filters['worker_search'] ?? '')));
        $unitId = filled($filters['organizational_unit_id'] ?? null)
            ? (int) $filters['organizational_unit_id']
            : null;

        $assignments = $batch->dailyAssignments
            ->filter(fn (DailyScheduleAssignment $assignment): bool => $assignment->day_type === 'unassigned')
            ->keyBy(fn (DailyScheduleAssignment $assignment): string => $assignment->employment_relationship_id.'|'.$assignment->work_date->toDateString());

        $rowsByRelationship = [];

        foreach ($this->expectedDates->handle($company, $batch) as $expected) {
            /** @var EmploymentRelationship $relationship */
            $relationship = $expected['relationship'];
            $relationship->loadMissing(['worker', 'center']);

            if (! $this->matchesWorkerSearch($relationship, $search)) {
                continue;
            }

            foreach ($expected['dates'] as $date) {
                /** @var DailyScheduleAssignment|null $assignment */
                $assignment = $assignments->get($relationship->id.'|'.$date);
                $unit = $assignment?->organizationalUnit;

                if (! $unit) {
                    $unit = $this->resolveUnits->handle($company, $relationship, $date)['primary'];
                }

                if ($unitId !== null && (int) ($unit?->id ?? 0) !== $unitId) {
                    continue;
                }

                if ($assignment === null && $this->hasNonPendingAssignment($batch, $relationship, $date)) {
                    continue;
                }

                $rowsByRelationship[$relationship->id] ??= [
                    'relationship' => $relationship,
                    'dates' => [],
                ];
                $rowsByRelationship[$relationship->id]['dates'][$date] = true;
            }
        }

        return collect($rowsByRelationship)
            ->map(fn (array $row): array => $this->csvRow($row['relationship'], $dates, $row['dates']))
            ->values()
            ->all();
    }

    private function hasNonPendingAssignment(ScheduleBatch $batch, EmploymentRelationship $relationship, string $date): bool
    {
        return $batch->dailyAssignments
            ->contains(fn (DailyScheduleAssignment $assignment): bool => $assignment->employment_relationship_id === $relationship->id
                && $assignment->work_date->toDateString() === $date
                && $assignment->day_type !== 'unassigned');
    }

    private function matchesWorkerSearch(EmploymentRelationship $relationship, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        return str_contains(mb_strtolower((string) $relationship->worker?->employee_code), $search)
            || str_contains(mb_strtolower((string) $relationship->worker?->full_name), $search);
    }

    /**
     * @return list<string>
     */
    private function batchDates(ScheduleBatch $batch): array
    {
        $start = CarbonImmutable::parse($batch->period_start->toDateString());
        $end = CarbonImmutable::parse($batch->period_end->toDateString());

        return collect(CarbonPeriod::create($start, $end))
            ->map(fn ($date): string => $date->toDateString())
            ->values()
            ->all();
    }

    /**
     * @param list<string> $dates
     * @param array<string, bool> $pendingDates
     * @return list<string>
     */
    private function csvRow(EmploymentRelationship $relationship, array $dates, array $pendingDates): array
    {
        $worker = $relationship->worker;

        return [
            (string) $worker?->employee_code,
            (string) $worker?->full_name,
            ...array_map(fn (string $date): string => isset($pendingDates[$date]) ? '' : '-', $dates),
        ];
    }
}
