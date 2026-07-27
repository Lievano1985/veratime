<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class CancelDraftScheduleBatchAction
{
    public function handle(User $actor, Company $company, ScheduleBatch $batch, string $reason): ScheduleBatch
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('El motivo para descartar el borrador es requerido.');
        }

        return DB::transaction(function () use ($actor, $company, $batch, $reason): ScheduleBatch {
            $batch = ScheduleBatch::query()
                ->with('company')
                ->lockForUpdate()
                ->findOrFail($batch->id);

            Gate::forUser($actor)->authorize('deleteDraft', $batch);

            if ($company->status !== 'active' || $batch->company_id !== $company->id) {
                throw new InvalidArgumentException('El lote no pertenece a la empresa activa.');
            }

            if ($batch->status !== 'draft') {
                throw new InvalidArgumentException('Solo se pueden descartar lotes en borrador.');
            }

            $batch->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            return $batch->refresh();
        });
    }
}
