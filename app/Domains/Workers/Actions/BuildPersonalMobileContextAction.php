<?php

namespace App\Domains\Workers\Actions;

use App\Domains\TimeRecords\Actions\ResolveCurrentTimeRecordStateAction;
use App\Models\Company;
use App\Models\WorkDay;
use App\Models\Worker;
use Carbon\CarbonImmutable;

class BuildPersonalMobileContextAction
{
    public function __construct(
        private readonly ResolveCurrentTimeRecordStateAction $resolveCurrentState,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Company $company, Worker $worker): array
    {
        $today = CarbonImmutable::now($company->timezone)->toDateString();
        $relationship = $worker->activeEmploymentRelationship()->with('center')->first();
        $timeRecord = $this->resolveCurrentState->handle($company, $worker, null, $relationship?->center);
        $workDay = WorkDay::query()
            ->with(['center', 'activeCalculation'])
            ->where('company_id', $company->id)
            ->where('worker_id', $worker->id)
            ->whereDate('work_date', $today)
            ->first();

        return [
            'company' => [
                'id' => (string) $company->id,
                'name' => $company->name,
                'timezone' => $company->timezone,
            ],
            'worker' => [
                'id' => (string) $worker->id,
                'employee_code' => $worker->employee_code,
                'full_name' => $worker->full_name,
            ],
            'permissions' => [
                'can_register_time_events' => true,
            ],
            'current_time_record' => [
                'state' => $timeRecord['state'],
                'allowed_actions' => $timeRecord['allowed_actions'],
                'local_date' => $timeRecord['local_date'],
                'timezone' => $timeRecord['timezone'],
                'last_event' => $timeRecord['last_event'] ? [
                    'id' => (string) $timeRecord['last_event']->id,
                    'event_type' => $timeRecord['last_event']->event_type,
                    'occurred_at' => $timeRecord['last_event']->occurred_at_utc?->toIso8601String(),
                ] : null,
            ],
            'today_work_day' => $workDay ? [
                'id' => (string) $workDay->id,
                'work_date' => $workDay->work_date?->toDateString(),
                'status' => $workDay->status,
                'schedule_status' => $workDay->schedule_status,
                'center' => $workDay->center ? [
                    'id' => (string) $workDay->center->id,
                    'name' => $workDay->center->name,
                ] : null,
                'active_calculation' => $workDay->activeCalculation ? [
                    'total_work_minutes' => $workDay->activeCalculation->total_work_minutes,
                ] : null,
            ] : null,
        ];
    }
}
