<?php

namespace App\Domains\Attendance\Actions;

use App\Models\AttendancePeriod;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CancelAttendancePeriodAction
{
    public function handle(Company $company, AttendancePeriod $period, string $reason, User $actor): AttendancePeriod
    {
        return DB::transaction(function () use ($company, $period, $reason, $actor): AttendancePeriod {
            $period = AttendancePeriod::query()->whereKey($period->id)->lockForUpdate()->firstOrFail();

            if ($period->company_id !== $company->id) {
                throw new InvalidArgumentException('El periodo debe pertenecer a la empresa activa.');
            }

            if ($period->status !== AttendancePeriod::STATUS_OPEN) {
                throw new InvalidArgumentException('Solo se pueden cancelar periodos abiertos.');
            }

            $period->forceFill([
                'status' => AttendancePeriod::STATUS_CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => trim($reason),
            ])->save();

            return $period->refresh();
        });
    }
}
