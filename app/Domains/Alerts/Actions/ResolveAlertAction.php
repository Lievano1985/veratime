<?php

namespace App\Domains\Alerts\Actions;

use App\Models\Alert;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ResolveAlertAction
{
    /**
     * @param  array{status: string, resolution: string}  $data
     */
    public function handle(Company $company, Alert $alert, User $actor, array $data): Alert
    {
        Gate::forUser($actor)->authorize('resolve', $alert);

        if ($company->status !== 'active' || $alert->company_id !== $company->id) {
            throw new InvalidArgumentException('La alerta no pertenece a la empresa activa.');
        }

        if ($alert->rule_code === 'incomplete_work_day' && ($alert->workDay?->activeCalculation?->result_snapshot['issues'] ?? []) !== []) {
            throw new InvalidArgumentException('Corrige los marcajes y recalcula la jornada antes de cerrar el registro incompleto.');
        }

        $status = (string) ($data['status'] ?? '');
        if (! in_array($status, [Alert::STATUS_JUSTIFIED, Alert::STATUS_CORRECTED, Alert::STATUS_CLOSED], true)) {
            throw new InvalidArgumentException('El dictamen de la alerta no es valido.');
        }

        $resolution = trim((string) ($data['resolution'] ?? ''));
        if (mb_strlen($resolution) < 5) {
            throw new InvalidArgumentException('El motivo del dictamen es obligatorio.');
        }

        return DB::transaction(function () use ($company, $alert, $actor, $status, $resolution): Alert {
            $locked = Alert::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->findOrFail($alert->id);

            $metadata = $locked->metadata ?? [];
            $history = $metadata['resolution_history'] ?? [];

            if ($locked->resolved_at || $locked->resolution) {
                $history[] = [
                    'previous_status' => $locked->status,
                    'previous_resolution' => $locked->resolution,
                    'previous_resolved_by' => $locked->resolved_by,
                    'previous_resolved_at' => $locked->resolved_at?->toDateTimeString(),
                    'changed_by' => $actor->id,
                    'changed_at' => CarbonImmutable::now('UTC')->toDateTimeString(),
                ];
            }

            $metadata['resolution'] = [
                'status' => $status,
                'actor_id' => $actor->id,
                'resolved_at' => CarbonImmutable::now('UTC')->toDateTimeString(),
            ];
            $metadata['resolution_history'] = $history;

            $locked->forceFill([
                'status' => $status,
                'resolution' => $resolution,
                'resolved_by' => $actor->id,
                'resolved_at' => CarbonImmutable::now('UTC'),
                'metadata' => $metadata,
            ])->save();

            if ($locked->rule_code === 'overtime_detected' && $status === Alert::STATUS_JUSTIFIED && $locked->work_day_id) {
                $day = WorkDay::query()->where('company_id', $company->id)->find($locked->work_day_id);
                if ($day && $day->active_calculation_id === $locked->work_day_calculation_id
                    && $day->activeCalculation?->status === WorkDayCalculation::STATUS_ACTIVE
                    && $day->activeCalculation->overtime_minutes > 0) {
                    $related = Alert::query()->where('company_id', $company->id)
                        ->where('work_day_id', $day->id)->where('work_day_calculation_id', $day->active_calculation_id)
                        ->where('rule_code', 'daily_limit_exceeded')->whereNull('metadata->duration_review_version')
                        ->whereIn('status', Alert::OPEN_STATUSES)
                        ->lockForUpdate()->get();
                    foreach ($related as $excess) {
                        $resolved = $this->handle($company, $excess, $actor, [
                            'status' => Alert::STATUS_CLOSED,
                            'resolution' => 'Seguimiento incluido en la autorización de tiempo extra. '.$resolution,
                        ]);
                        $linkedMetadata = $resolved->metadata ?? [];
                        $linkedMetadata['resolution']['overtime_authorization_alert_id'] = $locked->id;
                        $resolved->forceFill(['metadata' => $linkedMetadata])->save();
                    }
                }
            }

            $this->syncWorkDayStatus($company, $locked);

            return $locked->refresh()->load(['alertType', 'worker', 'workDay.activeCalculation', 'resolver']);
        });
    }

    private function syncWorkDayStatus(Company $company, Alert $alert): void
    {
        if (! $alert->work_day_id) {
            return;
        }

        $workDay = WorkDay::query()
            ->where('company_id', $company->id)
            ->lockForUpdate()
            ->find($alert->work_day_id);

        if (! $workDay) {
            return;
        }

        $openAlerts = Alert::query()
            ->where('company_id', $company->id)
            ->where('work_day_id', $workDay->id)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->count();

        if ($openAlerts > 0) {
            $workDay->forceFill(['status' => WorkDay::STATUS_WITH_ALERTS])->save();

            return;
        }

        $nextStatus = $workDay->active_calculation_id
            ? WorkDay::STATUS_CALCULATED
            : WorkDay::STATUS_PENDING;
        if (($workDay->activeCalculation?->result_snapshot['issues'] ?? []) !== []) {
            $nextStatus = WorkDay::STATUS_UNDER_REVIEW;
        }

        if ($workDay->active_calculation_id) {
            $calculationStatus = WorkDayCalculation::query()
                ->where('company_id', $company->id)
                ->whereKey($workDay->active_calculation_id)
                ->value('status');

            if ($calculationStatus !== WorkDayCalculation::STATUS_ACTIVE) {
                $nextStatus = WorkDay::STATUS_PENDING;
            }
        }

        $workDay->forceFill(['status' => $nextStatus])->save();
    }
}
