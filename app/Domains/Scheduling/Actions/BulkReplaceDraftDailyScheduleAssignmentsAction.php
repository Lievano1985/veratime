<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BulkReplaceDraftDailyScheduleAssignmentsAction
{
    public function __construct(
        private ReplaceDraftDailyScheduleAssignmentAction $replaceDraftDay,
        private BuildDailyScheduleSegmentsFromShiftTemplateAction $segmentsBuilder,
    ) {
    }

    /**
     * @param list<int> $employmentRelationshipIds
     * @param list<string> $workDates
     * @param array<string, mixed> $data
     */
    public function handle(
        Company $company,
        ScheduleBatch $batch,
        array $employmentRelationshipIds,
        array $workDates,
        array $data,
    ): array {
        return DB::transaction(function () use ($company, $batch, $employmentRelationshipIds, $workDates, $data): array {
            $batch = ScheduleBatch::query()->with('center')->lockForUpdate()->findOrFail($batch->id);
            if ($batch->company_id !== $company->id || $batch->status !== 'draft') {
                throw new InvalidArgumentException('Solo se pueden modificar lotes en borrador de la empresa activa.');
            }

            $relationships = EmploymentRelationship::query()
                ->where('company_id', $company->id)
                ->where('center_id', $batch->center_id)
                ->whereIn('id', $employmentRelationshipIds)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($relationships->count() !== count(array_unique($employmentRelationshipIds))) {
                throw new InvalidArgumentException('La seleccion contiene relaciones laborales no validas para el centro del lote.');
            }

            $changed = 0;
            foreach ($employmentRelationshipIds as $relationshipId) {
                $relationship = $relationships[$relationshipId];
                foreach ($workDates as $workDate) {
                    $dayPayload = ['work_date' => $workDate] + $data;
                    $segments = $this->segmentsFor($company, $batch, $dayPayload, $workDate);
                    $this->replaceDraftDay->handle($company, $batch, $relationship, $dayPayload, $segments);
                    $changed++;
                }
            }

            return ['changed' => $changed];
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function segmentsFor(Company $company, ScheduleBatch $batch, array $data, string $workDate): array
    {
        if (($data['day_type'] ?? null) !== 'shift') {
            return [];
        }

        $template = $company->shiftTemplates()
            ->where('status', 'active')
            ->whereKey((int) ($data['shift_template_id'] ?? 0))
            ->firstOrFail();

        return $this->segmentsBuilder->handle($template, $workDate, $batch->center->timezone ?: $company->timezone);
    }
}
