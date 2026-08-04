<?php

namespace App\Domains\WorkDays\Actions;

use App\Models\Company;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class RunDueWorkDaysAutoRefreshAction
{
    public function __construct(
        private readonly RunCompanyWorkDaysRefreshAction $refreshCompany,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(?CarbonImmutable $now = null): Collection
    {
        $nowUtc = ($now ?: CarbonImmutable::now('UTC'))->utc();
        $results = collect();

        Company::query()
            ->with('setting')
            ->where('status', 'active')
            ->whereHas('setting', fn ($query) => $query->whereNotNull('work_days_auto_refresh_time'))
            ->orderBy('id')
            ->chunkById(100, function ($companies) use ($nowUtc, $results): void {
                foreach ($companies as $company) {
                    if (! $this->isDue($company, $nowUtc)) {
                        continue;
                    }

                    $range = $this->refreshCompany->defaultAutomaticRange($company, $nowUtc);
                    $results->push($this->refreshCompany->handle(
                        $company,
                        $range['start_date'],
                        $range['end_date'],
                        mode: 'auto',
                    ));
                }
            });

        return $results;
    }

    private function isDue(Company $company, CarbonImmutable $nowUtc): bool
    {
        $settings = $company->setting;

        if (! $settings?->work_days_auto_refresh_time) {
            return false;
        }

        $timezone = $settings->default_timezone ?: $company->timezone;
        $localNow = $nowUtc->setTimezone($timezone);
        $configured = substr((string) $settings->work_days_auto_refresh_time, 0, 5);

        if ($localNow->format('H:i') !== $configured) {
            return false;
        }

        $last = $settings->work_days_last_refreshed_at;
        $lastMode = $settings->work_days_last_refresh_summary['mode'] ?? null;

        return $lastMode !== 'auto'
            || ! $last
            || $last->setTimezone($timezone)->toDateString() !== $localNow->toDateString();
    }
}
