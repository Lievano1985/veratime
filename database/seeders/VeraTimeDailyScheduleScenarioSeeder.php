<?php

namespace Database\Seeders;

use App\Domains\Scheduling\Actions\CreateScheduleBatchAction;
use App\Domains\Scheduling\Actions\GenerateDraftScheduleBatchFromProfilesAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class VeraTimeDailyScheduleScenarioSeeder extends Seeder
{
    private const PERIOD_START = '2026-08-03';
    private const PERIOD_END = '2026-08-16';

    /**
     * @var array<string, string>
     */
    private const SCENARIOS = [
        'VTSP-OFFICE' => 'office',
        'VTSP-CYCLE' => 'cycle',
        'VTSP-FLEX' => 'flex',
        'VTSP-ONCALL' => 'oncall',
        'VTSP-STORE' => 'store',
        'VTSP-NOPROFILE' => 'noprofile',
    ];

    public function run(): void
    {
        if ($this->missingBaseScenario()) {
            $this->call(VeraTimeScheduleProfileScenarioSeeder::class);
        }

        foreach (self::SCENARIOS as $taxId => $slug) {
            $company = Company::query()->where('tax_id', $taxId)->firstOrFail();
            $actor = User::query()->where('email', "rh.{$slug}.demo@veratime.local")->firstOrFail();

            Center::query()
                ->where('company_id', $company->id)
                ->where('status', 'active')
                ->orderBy('id')
                ->get()
                ->each(function (Center $center) use ($company, $actor): void {
                    $batch = $this->batch($company, $center, $actor);

                    app(GenerateDraftScheduleBatchFromProfilesAction::class)->handle(
                        $actor,
                        $company,
                        $batch,
                        GenerateDraftScheduleBatchFromProfilesAction::MODE_MISSING_ONLY,
                    );
                });
        }
    }

    private function missingBaseScenario(): bool
    {
        return Company::query()
            ->whereIn('tax_id', array_keys(self::SCENARIOS))
            ->count() !== count(self::SCENARIOS);
    }

    private function batch(Company $company, Center $center, User $actor): ScheduleBatch
    {
        $batch = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('center_id', $center->id)
            ->whereDate('period_start', self::PERIOD_START)
            ->whereDate('period_end', self::PERIOD_END)
            ->whereNull('version')
            ->where('status', 'draft')
            ->first();

        if ($batch) {
            if ($batch->status !== 'draft') {
                throw new InvalidArgumentException('El batch demo de programacion diaria debe permanecer en draft.');
            }

            return $batch;
        }

        return app(CreateScheduleBatchAction::class)->handle($company, $center, [
            'period_start' => self::PERIOD_START,
            'period_end' => self::PERIOD_END,
            'creation_source' => 'profile',
            'notes' => 'Escenario demo local F2: generacion draft desde perfiles.',
        ], $actor);
    }
}
