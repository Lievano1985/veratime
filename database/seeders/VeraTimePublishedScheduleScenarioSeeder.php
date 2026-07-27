<?php

namespace Database\Seeders;

use App\Domains\Scheduling\Actions\PublishScheduleBatchAction;
use App\Domains\Scheduling\Actions\ValidateScheduleBatchForPublicationAction;
use App\Domains\Scheduling\Actions\VerifyPublishedScheduleBatchSnapshotAction;
use App\Models\Company;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class VeraTimePublishedScheduleScenarioSeeder extends Seeder
{
    private const PERIOD_START = '2026-08-03';
    private const PERIOD_END = '2026-08-16';

    /**
     * @var array<string, string>
     */
    private const PUBLISHABLE_SCENARIOS = [
        'VTSP-OFFICE' => 'office',
        'VTSP-CYCLE' => 'cycle',
        'VTSP-FLEX' => 'flex',
        'VTSP-ONCALL' => 'oncall',
    ];

    /**
     * @var array<string, string>
     */
    private const DRAFT_ONLY_SCENARIOS = [
        'VTSP-STORE' => 'store',
        'VTSP-NOPROFILE' => 'noprofile',
    ];

    public function run(): void
    {
        if ($this->missingDailyScenarios()) {
            $this->call(VeraTimeDailyScheduleScenarioSeeder::class);
        }

        foreach (self::PUBLISHABLE_SCENARIOS as $taxId => $slug) {
            $company = Company::query()->where('tax_id', $taxId)->firstOrFail();
            $actor = User::query()->where('email', "rh.{$slug}.demo@veratime.local")->firstOrFail();
            $batch = $this->batch($company);

            if ($batch->status === 'published') {
                $verification = app(VerifyPublishedScheduleBatchSnapshotAction::class)->handle($company, $batch);
                if (! $verification->valid) {
                    throw new InvalidArgumentException('El snapshot publicado demo no es integro.');
                }

                continue;
            }

            if ($batch->status === 'superseded'
                && ScheduleBatch::query()
                    ->where('company_id', $company->id)
                    ->whereDate('period_start', self::PERIOD_START)
                    ->whereDate('period_end', self::PERIOD_END)
                    ->where('status', 'published')
                    ->where('version', '>', 1)
                    ->exists()) {
                continue;
            }

            if ($batch->status !== 'draft') {
                throw new InvalidArgumentException('El batch demo publicado debe estar en borrador o publicado.');
            }

            app(PublishScheduleBatchAction::class)->handle($actor, $company, $batch);
        }

        foreach (self::DRAFT_ONLY_SCENARIOS as $taxId => $slug) {
            $company = Company::query()->where('tax_id', $taxId)->firstOrFail();
            $actor = User::query()->where('email', "rh.{$slug}.demo@veratime.local")->firstOrFail();
            $batch = $this->batch($company);
            $validation = app(ValidateScheduleBatchForPublicationAction::class)->handle($actor, $company, $batch);

            if ($batch->status !== 'draft' || $validation->valid()) {
                throw new InvalidArgumentException('El batch demo con pendientes debe permanecer en draft.');
            }
        }
    }

    private function missingDailyScenarios(): bool
    {
        $taxIds = array_merge(array_keys(self::PUBLISHABLE_SCENARIOS), array_keys(self::DRAFT_ONLY_SCENARIOS));
        $companyIds = Company::query()->whereIn('tax_id', $taxIds)->pluck('id');

        if ($companyIds->count() !== count($taxIds)) {
            return true;
        }

        return ScheduleBatch::query()
            ->whereIn('company_id', $companyIds)
            ->whereDate('period_start', self::PERIOD_START)
            ->whereDate('period_end', self::PERIOD_END)
            ->distinct('company_id')
            ->count('company_id') !== count($taxIds);
    }

    private function batch(Company $company): ScheduleBatch
    {
        $published = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->whereDate('period_start', self::PERIOD_START)
            ->whereDate('period_end', self::PERIOD_END)
            ->whereNotNull('version')
            ->whereIn('status', ['published', 'superseded'])
            ->orderByDesc('version')
            ->first();

        if ($published) {
            return $published;
        }

        return ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->whereDate('period_start', self::PERIOD_START)
            ->whereDate('period_end', self::PERIOD_END)
            ->whereNull('version')
            ->whereNull('previous_batch_id')
            ->where('status', 'draft')
            ->firstOrFail();
    }
}
