<?php

namespace App\Domains\WorkDays\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\CompanySetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunCompanyWorkDaysRefreshAction
{
    public function __construct(
        private readonly RefreshWorkDaysForDateRangeAction $refresh,
    ) {}

    /**
     * @return array{company_id: int, start_date: string, end_date: string, center_id: ?int, mode: string, status: string, scheduled: int, unscheduled: int, total: int}
     */
    public function handle(Company $company, string $startDate, string $endDate, ?Center $center = null, string $mode = 'manual'): array
    {
        $settings = $company->setting ?: $company->setting()->create(Company::defaultSettings());

        try {
            $result = $this->refresh->handle($company, $startDate, $endDate, $center);

            $summary = [
                'company_id' => $company->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'center_id' => $center?->id,
                'mode' => $mode,
                'status' => 'ok',
                'scheduled' => $result['scheduled'],
                'unscheduled' => $result['unscheduled'],
                'total' => $result['total'],
            ];

            $this->recordResult($settings, $summary);

            return $summary;
        } catch (Throwable $exception) {
            $summary = [
                'company_id' => $company->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'center_id' => $center?->id,
                'mode' => $mode,
                'status' => 'failed',
                'scheduled' => 0,
                'unscheduled' => 0,
                'total' => 0,
                'error' => $exception->getMessage(),
            ];

            $this->recordResult($settings, $summary);

            throw $exception;
        }
    }

    /**
     * @return array{start_date: string, end_date: string}
     */
    public function defaultAutomaticRange(Company $company, ?CarbonImmutable $now = null): array
    {
        $timezone = $company->setting?->default_timezone ?: $company->timezone;
        $today = ($now ?: CarbonImmutable::now($timezone))->setTimezone($timezone)->startOfDay();

        return [
            'start_date' => $today->subDays(7)->toDateString(),
            'end_date' => $today->addDays(14)->toDateString(),
        ];
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function recordResult(CompanySetting $settings, array $summary): void
    {
        DB::transaction(function () use ($settings, $summary): void {
            $settings->refresh();
            $settings->forceFill([
                'work_days_last_refreshed_at' => CarbonImmutable::now('UTC'),
                'work_days_last_refresh_status' => $summary['status'],
                'work_days_last_refresh_summary' => $summary,
            ])->save();
        });
    }
}
