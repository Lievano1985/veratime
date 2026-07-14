<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\LaborCondition;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateOrReplaceLaborConditionAction
{
    public function handle(Company $company, Worker $worker, EmploymentRelationship $relationship, array $data): LaborCondition
    {
        $this->assertBelongsToCompany($company, $worker, $relationship);

        return DB::transaction(function () use ($company, $relationship, $data): LaborCondition {
            $effectiveFrom = CarbonImmutable::parse($data['effective_from'])->startOfDay();
            $effectiveTo = isset($data['effective_to']) && $data['effective_to'] !== ''
                ? CarbonImmutable::parse($data['effective_to'])->startOfDay()
                : null;

            if ($effectiveTo && $effectiveTo->lessThan($effectiveFrom)) {
                throw new InvalidArgumentException('La fecha final de la condicion laboral debe ser igual o posterior a la fecha inicial.');
            }

            if (($data['status'] ?? 'active') === 'active') {
                $this->replaceOverlappingActiveCondition($relationship, $effectiveFrom, $effectiveTo);
            }

            return $company->laborConditions()->create([
                'employment_relationship_id' => $relationship->id,
                'schedule_id' => $data['schedule_id'] ?? null,
                'work_modality' => $data['work_modality'],
                'weekly_hours' => $data['weekly_hours'] ?? null,
                'rest_day_of_week' => $data['rest_day_of_week'] ?? null,
                'policy_id' => $data['policy_id'] ?? null,
                'effective_from' => $effectiveFrom->toDateString(),
                'effective_to' => $effectiveTo?->toDateString(),
                'status' => $data['status'] ?? 'active',
                'metadata' => $data['metadata'] ?? [],
            ]);
        });
    }

    private function assertBelongsToCompany(Company $company, Worker $worker, EmploymentRelationship $relationship): void
    {
        if ($worker->company_id !== $company->id
            || $relationship->company_id !== $company->id
            || $relationship->worker_id !== $worker->id) {
            throw new InvalidArgumentException('La condicion laboral debe pertenecer a la relacion laboral de la empresa activa.');
        }
    }

    private function replaceOverlappingActiveCondition(
        EmploymentRelationship $relationship,
        CarbonImmutable $effectiveFrom,
        ?CarbonImmutable $effectiveTo,
    ): void {
        $rangeEnd = $effectiveTo?->toDateString() ?? '9999-12-31';

        $overlappingConditions = $relationship->laborConditions()
            ->where('status', 'active')
            ->where('effective_from', '<=', $rangeEnd)
            ->where(function ($query) use ($effectiveFrom): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $effectiveFrom->toDateString());
            })
            ->orderBy('effective_from')
            ->lockForUpdate()
            ->get();

        foreach ($overlappingConditions as $condition) {
            $conditionStart = CarbonImmutable::parse($condition->effective_from)->startOfDay();

            if ($conditionStart->greaterThanOrEqualTo($effectiveFrom)) {
                throw new InvalidArgumentException('La nueva condicion laboral debe iniciar despues de la condicion activa.');
            }

            $condition->forceFill([
                'status' => 'replaced',
                'effective_to' => $effectiveFrom->subDay()->toDateString(),
            ])->save();
        }
    }
}
