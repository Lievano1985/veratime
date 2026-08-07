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
     * @param array{status: string, resolution: string} $data
     */
    public function handle(Company $company, Alert $alert, User $actor, array $data): Alert
    {
        Gate::forUser($actor)->authorize('resolve', $alert);

        if ($company->status !== 'active' || $alert->company_id !== $company->id) {
            throw new InvalidArgumentException('La alerta no pertenece a la empresa activa.');
        }

        $status = (string) ($data['status'] ?? '');
        if (! in_array($status, [Alert::STATUS_JUSTIFIED, Alert::STATUS_CORRECTED, Alert::STATUS_CLOSED], true)) {
            throw new InvalidArgumentException('El dictamen de la alerta no es valido.');
        }

        $resolution = trim((string) ($data['resolution'] ?? ''));
        if (mb_strlen($resolution) < 5) {
            throw new InvalidArgumentException('El motivo del dictamen es obligatorio.');
        }

        if (! in_array($alert->status, Alert::OPEN_STATUSES, true)) {
            throw new InvalidArgumentException('Solo se pueden dictaminar alertas abiertas.');
        }

        return DB::transaction(function () use ($company, $alert, $actor, $status, $resolution): Alert {
            $locked = Alert::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->findOrFail($alert->id);

            if (! in_array($locked->status, Alert::OPEN_STATUSES, true)) {
                throw new InvalidArgumentException('Solo se pueden dictaminar alertas abiertas.');
            }

            $metadata = $locked->metadata ?? [];
            $metadata['resolution'] = [
                'status' => $status,
                'actor_id' => $actor->id,
                'resolved_at' => CarbonImmutable::now('UTC')->toDateTimeString(),
            ];

            $locked->forceFill([
                'status' => $status,
                'resolution' => $resolution,
                'resolved_by' => $actor->id,
                'resolved_at' => CarbonImmutable::now('UTC'),
                'metadata' => $metadata,
            ])->save();

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
