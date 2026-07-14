<?php

namespace App\Domains\Workers\Actions;

use App\Models\Company;
use App\Models\LaborCondition;
use App\Models\Worker;
use InvalidArgumentException;

class UpdateLaborConditionAction
{
    public function __construct(
        private readonly CreateOrReplaceLaborConditionAction $createOrReplaceLaborConditionAction,
    ) {}

    public function handle(Company $company, Worker $worker, LaborCondition $condition, array $data): LaborCondition
    {
        $relationship = $condition->employmentRelationship;

        if (! $relationship
            || $condition->company_id !== $company->id
            || $worker->company_id !== $company->id
            || $relationship->worker_id !== $worker->id) {
            throw new InvalidArgumentException('La condicion laboral debe pertenecer a la relacion laboral de la empresa activa.');
        }

        if ($this->relevantHistoricalFieldsChanged($condition, $data)) {
            return $this->createOrReplaceLaborConditionAction->handle($company, $worker, $relationship, $data);
        }

        $condition->fill([
            'status' => $data['status'] ?? $condition->status,
            'metadata' => $data['metadata'] ?? $condition->metadata ?? [],
        ]);
        $condition->save();

        return $condition->refresh();
    }

    private function relevantHistoricalFieldsChanged(LaborCondition $condition, array $data): bool
    {
        return (string) ($condition->schedule_id ?? '') !== (string) ($data['schedule_id'] ?? '')
            || (string) $condition->work_modality !== (string) ($data['work_modality'] ?? '')
            || (string) ($condition->weekly_hours ?? '') !== (string) ($data['weekly_hours'] ?? '')
            || (string) ($condition->rest_day_of_week ?? '') !== (string) ($data['rest_day_of_week'] ?? '')
            || (string) ($condition->policy_id ?? '') !== (string) ($data['policy_id'] ?? '')
            || $condition->effective_from?->toDateString() !== (string) ($data['effective_from'] ?? '')
            || (string) ($condition->effective_to?->toDateString() ?? '') !== (string) ($data['effective_to'] ?? '');
    }
}
