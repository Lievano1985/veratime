<?php

namespace App\Domains\Attendance\Actions;

use App\Models\AttendancePeriod;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CloseAttendancePeriodAction
{
    public function __construct(
        private readonly ValidateAttendancePeriodForClosingAction $validator,
        private readonly BuildAttendancePeriodReportAction $reportBuilder,
    ) {
    }

    public function handle(Company $company, AttendancePeriod $period, User $actor): AttendancePeriod
    {
        return DB::transaction(function () use ($company, $period, $actor): AttendancePeriod {
            $period = AttendancePeriod::query()
                ->where('company_id', $company->id)
                ->whereKey($period->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($period->status === AttendancePeriod::STATUS_CLOSED) {
                throw new InvalidArgumentException('El periodo ya esta cerrado.');
            }

            $validation = $this->validator->handle($company, $period, $actor);
            if (! ($validation['ready_to_close'] ?? false)) {
                throw new InvalidArgumentException('El periodo tiene bloqueantes pendientes en Jornadas.');
            }

            $period->refresh();
            $report = $this->reportBuilder->handle($period);
            $snapshot = [
                'schema_version' => 1,
                'closed_at' => now()->toISOString(),
                'closed_by' => $actor->id,
                'validation' => $validation,
                'report' => $report,
            ];

            $canonicalJson = $this->canonicalJson($snapshot);

            $period->forceFill([
                'status' => AttendancePeriod::STATUS_CLOSED,
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'validation_summary' => $validation,
                'report_summary' => $report,
                'snapshot_schema_version' => 'attendance_period.v1',
                'snapshot_canonical_json' => $canonicalJson,
                'snapshot_sha256' => hash('sha256', $canonicalJson),
            ])->save();

            return $period->refresh()->load(['center', 'scopes.organizationalUnit', 'closedBy']);
        });
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function canonicalJson(array $payload): string
    {
        $this->sortKeys($payload);

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @param mixed $value
     */
    private function sortKeys(&$value): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as &$child) {
            $this->sortKeys($child);
        }

        if (array_is_list($value)) {
            return;
        }

        ksort($value);
    }
}
