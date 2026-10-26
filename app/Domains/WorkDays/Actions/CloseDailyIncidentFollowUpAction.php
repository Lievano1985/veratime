<?php

namespace App\Domains\WorkDays\Actions;

use App\Domains\Alerts\Actions\ResolveAlertAction;
use App\Domains\WorkDays\Support\WorkDayWorkflow;
use App\Models\Alert;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkDay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CloseDailyIncidentFollowUpAction
{
    public function __construct(private readonly ResolveAlertAction $resolve) {}

    public function handle(Company $company, WorkDay $day, User $actor, string $note): int
    {
        Gate::forUser($actor)->authorize('view', $day);
        if ($day->company_id !== $company->id || $company->status !== 'active') {
            throw new InvalidArgumentException('La jornada no pertenece a la empresa activa.');
        }
        if (mb_strlen(trim($note)) < 5 || mb_strlen($note) > 1000) {
            throw new InvalidArgumentException('Describe las acciones de seguimiento (de 5 a 1000 caracteres).');
        }

        return DB::transaction(function () use ($company, $day, $actor, $note): int {
            $locked = WorkDay::where('company_id', $company->id)->lockForUpdate()->findOrFail($day->id);
            if (! WorkDayWorkflow::isCompleted($locked)) {
                throw new InvalidArgumentException('Primero corrige los marcajes y completa la jornada.');
            }
            $alerts = Alert::where('company_id', $company->id)->where('work_day_id', $locked->id)
                ->whereNotIn('rule_code', [...WorkDayWorkflow::ATTENDANCE_RULES, ...WorkDayWorkflow::WEEKLY_RULES, ...WorkDayWorkflow::ADMINISTRATIVE_AUTHORIZATION_RULES])
                ->whereIn('status', Alert::OPEN_STATUSES)->lockForUpdate()->get();
            foreach ($alerts as $alert) {
                Gate::forUser($actor)->authorize('resolve', $alert);
            }
            foreach ($alerts as $alert) {
                $this->resolve->handle($company, $alert, $actor, ['status' => Alert::STATUS_CLOSED, 'resolution' => trim($note)]);
            }

            return $alerts->count();
        });
    }
}
