<?php

namespace Tests\Feature\LegalRules;

use App\Domains\LegalRules\Actions\ClassifyWorkDayCalculationAction;
use App\Domains\LegalRules\Actions\ClassifyWorkDayCalculationsForDateRangeAction;
use App\Models\Company;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Database\Seeders\LegalRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkDayLegalClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LegalRuleSeeder::class);
    }

    public function test_classifies_diurnal_work_day_from_daytime_minutes(): void
    {
        $calculation = $this->calculationWithIntervals([
            ['start_utc' => '2026-08-03 14:00:00', 'end_utc' => '2026-08-03 22:00:00', 'minutes' => 480],
        ]);

        $classified = app(ClassifyWorkDayCalculationAction::class)->handle($calculation);

        $this->assertSame(WorkDayCalculation::CLASSIFICATION_DIURNAL, $classified->classification);
        $this->assertSame(0, $classified->night_minutes);
        $this->assertTrue($classified->rules_snapshot['legal_engine_applied']);
        $this->assertSame(480, $classified->result_snapshot['legal_classification']['day_minutes']);
        $this->assertSame(480, $classified->result_snapshot['legal_classification']['daily_limit_minutes']);
    }

    public function test_classifies_mixed_work_day_when_night_minutes_are_below_threshold(): void
    {
        $calculation = $this->calculationWithIntervals([
            ['start_utc' => '2026-08-04 01:00:00', 'end_utc' => '2026-08-04 04:00:00', 'minutes' => 180],
        ]);

        $classified = app(ClassifyWorkDayCalculationAction::class)->handle($calculation);

        $this->assertSame(WorkDayCalculation::CLASSIFICATION_MIXED, $classified->classification);
        $this->assertSame(120, $classified->night_minutes);
        $this->assertSame(60, $classified->result_snapshot['legal_classification']['day_minutes']);
        $this->assertSame(450, $classified->result_snapshot['legal_classification']['daily_limit_minutes']);
    }

    public function test_classifies_nocturnal_work_day_when_night_minutes_reach_mixed_threshold(): void
    {
        $calculation = $this->calculationWithIntervals([
            ['start_utc' => '2026-08-04 01:00:00', 'end_utc' => '2026-08-04 05:30:00', 'minutes' => 270],
        ]);

        $classified = app(ClassifyWorkDayCalculationAction::class)->handle($calculation);

        $this->assertSame(WorkDayCalculation::CLASSIFICATION_NOCTURNAL, $classified->classification);
        $this->assertSame(210, $classified->night_minutes);
        $this->assertSame(60, $classified->result_snapshot['legal_classification']['day_minutes']);
        $this->assertSame(420, $classified->result_snapshot['legal_classification']['daily_limit_minutes']);
    }

    public function test_subtracts_break_minutes_from_day_and_night_classification(): void
    {
        $calculation = $this->calculationWithIntervals(
            workIntervals: [
                ['start_utc' => '2026-08-18 04:00:00', 'end_utc' => '2026-08-18 12:00:00', 'minutes' => 480],
            ],
            breakIntervals: [
                ['start_utc' => '2026-08-18 08:00:00', 'end_utc' => '2026-08-18 08:30:00', 'minutes' => 30],
            ],
            totalMinutes: 450,
            breakMinutes: 30,
        );

        $classified = app(ClassifyWorkDayCalculationAction::class)->handle($calculation);

        $this->assertSame(WorkDayCalculation::CLASSIFICATION_NOCTURNAL, $classified->classification);
        $this->assertSame(450, $classified->night_minutes);
        $this->assertSame(0, $classified->result_snapshot['legal_classification']['day_minutes']);
    }

    public function test_incomplete_calculation_stays_pending_for_legal_classification(): void
    {
        $calculation = $this->calculationWithIntervals(
            workIntervals: [],
            issues: ['missing_clock_out'],
            totalMinutes: 0,
        );

        $classified = app(ClassifyWorkDayCalculationAction::class)->handle($calculation);

        $this->assertSame(WorkDayCalculation::CLASSIFICATION_PENDING, $classified->classification);
        $this->assertTrue($classified->explanation['legal_pending']);
        $this->assertArrayNotHasKey('legal_classification', $classified->result_snapshot);
    }

    public function test_zero_minutes_calculation_stays_pending_for_legal_classification(): void
    {
        $calculation = $this->calculationWithIntervals(
            workIntervals: [
                ['start_utc' => '2026-08-03 14:00:00', 'end_utc' => '2026-08-03 14:00:00', 'minutes' => 0],
            ],
            totalMinutes: 0,
        );

        $classified = app(ClassifyWorkDayCalculationAction::class)->handle($calculation);

        $this->assertSame(WorkDayCalculation::CLASSIFICATION_PENDING, $classified->classification);
        $this->assertTrue($classified->explanation['legal_pending']);
    }

    public function test_range_classification_is_company_scoped(): void
    {
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $otherCompany = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $this->calculationWithIntervals([
            ['start_utc' => '2026-08-03 14:00:00', 'end_utc' => '2026-08-03 22:00:00', 'minutes' => 480],
        ], company: $company);
        $otherCalculation = $this->calculationWithIntervals([
            ['start_utc' => '2026-08-03 14:00:00', 'end_utc' => '2026-08-03 22:00:00', 'minutes' => 480],
        ], company: $otherCompany);

        $summary = app(ClassifyWorkDayCalculationsForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-03');

        $this->assertSame(['total' => 1, 'classified' => 1, 'pending' => 0], $summary);
        $this->assertSame(WorkDayCalculation::CLASSIFICATION_PENDING, $otherCalculation->refresh()->classification);
    }

    /**
     * @param list<array{start_utc: string, end_utc: string, minutes: int}> $workIntervals
     * @param list<array{start_utc: string, end_utc: string, minutes: int}> $breakIntervals
     * @param list<string> $issues
     */
    private function calculationWithIntervals(array $workIntervals, array $breakIntervals = [], array $issues = [], int $totalMinutes = 480, int $breakMinutes = 0, ?Company $company = null): WorkDayCalculation
    {
        $company ??= Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $workDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'work_date' => '2026-08-03',
            'timezone' => 'America/Mexico_City',
            'status' => $issues === [] ? WorkDay::STATUS_CALCULATED : WorkDay::STATUS_UNDER_REVIEW,
        ]);
        $calculation = WorkDayCalculation::factory()->create([
            'company_id' => $company->id,
            'work_day_id' => $workDay->id,
            'total_work_minutes' => $totalMinutes,
            'ordinary_minutes' => $totalMinutes,
            'break_minutes' => $breakMinutes,
            'classification' => WorkDayCalculation::CLASSIFICATION_PENDING,
            'rules_snapshot' => [
                'schema_version' => 1,
                'legal_engine_applied' => false,
            ],
            'result_snapshot' => [
                'schema_version' => 1,
                'work_intervals' => $workIntervals,
                'break_intervals' => $breakIntervals,
                'issues' => $issues,
            ],
            'explanation' => [
                'schema_version' => 1,
                'legal_pending' => true,
            ],
        ]);

        $workDay->forceFill(['active_calculation_id' => $calculation->id])->save();

        return $calculation;
    }
}
