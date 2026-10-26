<?php

namespace Tests\Feature\LegalRules;

use App\Domains\Alerts\Actions\EvaluateWorkDayAlertsAction;
use App\Domains\LegalRules\Actions\ApplyOrdinaryOvertimeForDateRangeAction;
use App\Domains\LegalRules\Actions\ApplySpecialLegalCasesForDateRangeAction;
use App\Models\Alert;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\LegalParameter;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Database\Seeders\LegalRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkDayOrdinaryOvertimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LegalRuleSeeder::class);
    }

    public function test_daily_limit_splits_ordinary_and_overtime_minutes(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $calculation = $this->calculatedWorkDay($company, $relationship, '2026-08-03', 540);

        $summary = app(ApplyOrdinaryOvertimeForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-03');

        $calculation->refresh();
        $this->assertSame(['total' => 1, 'calculated' => 1, 'pending' => 0], $summary);
        $this->assertSame(480, $calculation->ordinary_minutes);
        $this->assertSame(60, $calculation->overtime_minutes);
        $this->assertSame(60, $calculation->overtime_double_minutes);
        $this->assertSame(0, $calculation->overtime_triple_minutes);
        $this->assertSame(480, $calculation->result_snapshot['ordinary_overtime']['daily_limit_minutes']);
        $this->assertSame('country_rule', $calculation->rules_snapshot['ordinary_overtime']['daily_limit']['source']);
    }

    public function test_company_parameter_can_define_more_favorable_daily_limit(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        LegalParameter::factory()->forCompany($company)->create([
            'code' => 'company_daily_limit_diurnal_minutes',
            'value' => ['minutes' => 450],
            'effective_from' => '2026-08-01',
            'status' => LegalParameter::STATUS_ACTIVE,
        ]);
        $calculation = $this->calculatedWorkDay($company, $relationship, '2026-08-03', 480);

        app(ApplyOrdinaryOvertimeForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-03');

        $calculation->refresh();
        $this->assertSame(450, $calculation->ordinary_minutes);
        $this->assertSame(30, $calculation->overtime_minutes);
        $this->assertSame('company_parameter', $calculation->rules_snapshot['ordinary_overtime']['daily_limit']['source']);
    }

    public function test_weekly_excess_is_separate_from_daily_overtime(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $calculations = collect(range(0, 6))->map(fn (int $offset): WorkDayCalculation => $this->calculatedWorkDay(
            $company,
            $relationship,
            '2026-08-0'.($offset + 3),
            480,
        ));

        $summary = app(ApplyOrdinaryOvertimeForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-09');

        $last = $calculations->last()->refresh();
        $this->assertSame(['total' => 7, 'calculated' => 7, 'pending' => 0], $summary);
        $this->assertSame(0, $last->ordinary_minutes);
        $this->assertSame(480, $last->overtime_minutes);
        $this->assertSame(480, $last->overtime_double_minutes);
        $this->assertSame(0, $last->overtime_triple_minutes);
        $this->assertSame(2880, $last->result_snapshot['ordinary_overtime']['weekly_limit_minutes']);
        $this->assertSame(2880, $last->result_snapshot['ordinary_overtime']['weekly_ordinary_before_minutes']);
        $this->assertSame(480, $last->result_snapshot['ordinary_overtime']['weekly_ordinary_excess_minutes']);
        $this->assertSame(0, data_get($last->result_snapshot, 'ordinary_overtime.daily_overtime_minutes'));
        $this->assertSame(480, data_get($last->result_snapshot, 'ordinary_overtime.weekly_overtime_minutes'));
        $this->assertSame(2880, $calculations->sum(fn ($calculation) => $calculation->refresh()->ordinary_minutes));
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $last->workDay->fresh());
        $this->assertFalse($last->workDay->alerts()->where('rule_code', 'daily_overtime_over_three_hours')->exists());
        $extraAlert = $last->workDay->alerts()->where('rule_code', 'overtime_detected')->firstOrFail();
        $this->assertSame(0, $extraAlert->metadata['daily_overtime_minutes']);
        $this->assertSame(480, $extraAlert->metadata['weekly_overtime_minutes']);
    }

    public function test_worked_rest_days_keep_rest_sunday_and_weekly_excess_separate_from_overtime(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $calculations = collect(range(0, 6))->map(fn (int $offset): WorkDayCalculation => $this->calculatedWorkDay(
            $company, $relationship, CarbonImmutable::parse('2026-08-03')->addDays($offset)->toDateString(), 480,
        ));
        foreach ([5, 6] as $index) {
            $calculations[$index]->workDay->update(['day_type' => 'rest']);
        }
        app(ApplyOrdinaryOvertimeForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-09');
        app(ApplySpecialLegalCasesForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-09');
        foreach ($calculations as $calculation) {
            app(EvaluateWorkDayAlertsAction::class)->handle($company, $calculation->workDay->fresh());
        }
        foreach ([5, 6] as $index) {
            $calculation = $calculations[$index]->fresh();
            $this->assertSame(480, $calculation->total_work_minutes);
            $this->assertSame(480, $calculation->ordinary_minutes);
            $this->assertSame(0, $calculation->overtime_minutes);
            $this->assertSame(480, data_get($calculation->result_snapshot, 'special_legal_cases.scheduled_rest.worked_minutes'));
            $this->assertTrue($calculation->workDay->alerts()->where('rule_code', 'scheduled_rest_work')->whereIn('status', Alert::OPEN_STATUSES)->exists());
            $this->assertFalse($calculation->workDay->alerts()->whereIn('rule_code', ['overtime_detected', 'daily_overtime_over_three_hours'])->whereIn('status', Alert::OPEN_STATUSES)->exists());
        }
        $this->assertSame(480, $calculations[6]->fresh()->sunday_minutes);
        $this->assertFalse(Alert::where('company_id', $company->id)->where('rule_code', 'weekly_hours_exceeded')->exists());
        $this->assertSame(0, data_get($calculations[6]->fresh()->result_snapshot, 'ordinary_overtime.weekly_overtime_minutes'));
        $this->assertSame(1, Alert::where('company_id', $company->id)->where('rule_code', 'weekly_rest_missing')->count());
    }

    public function test_weekly_limit_uses_the_2027_legal_version_for_the_worked_week(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $calculations = collect(range(0, 5))->map(fn (int $offset): WorkDayCalculation => $this->calculatedWorkDay(
            $company,
            $relationship,
            '2027-01-0'.($offset + 4),
            480,
        ));

        app(ApplyOrdinaryOvertimeForDateRangeAction::class)->handle($company, '2027-01-04', '2027-01-09');

        $last = $calculations->last()->refresh();
        $this->assertSame(360, $last->ordinary_minutes);
        $this->assertSame(120, $last->overtime_minutes);
        $this->assertSame(120, $last->result_snapshot['ordinary_overtime']['weekly_ordinary_excess_minutes']);
        $this->assertSame(2760, $last->result_snapshot['ordinary_overtime']['weekly_limit_minutes']);
        $this->assertSame(2, $last->rules_snapshot['ordinary_overtime']['weekly_limit']['version']);
    }

    public function test_weekly_limit_uses_the_2030_legal_version_for_the_worked_week(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $calculations = collect(range(0, 5))->map(fn (int $offset): WorkDayCalculation => $this->calculatedWorkDay(
            $company,
            $relationship,
            CarbonImmutable::parse('2030-01-07')->addDays($offset)->toDateString(),
            480,
        ));

        app(ApplyOrdinaryOvertimeForDateRangeAction::class)->handle($company, '2030-01-07', '2030-01-12');

        $last = $calculations->last()->refresh();
        $this->assertSame(0, $last->ordinary_minutes);
        $this->assertSame(480, $last->overtime_minutes);
        $this->assertSame(480, $last->result_snapshot['ordinary_overtime']['weekly_ordinary_excess_minutes']);
        $this->assertSame(2400, $last->result_snapshot['ordinary_overtime']['weekly_limit_minutes']);
        $this->assertSame(5, $last->rules_snapshot['ordinary_overtime']['weekly_limit']['version']);
    }

    public function test_company_weekly_limit_cannot_weaken_the_future_legal_limit(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        LegalParameter::factory()->forCompany($company)->create([
            'code' => 'company_weekly_limit_minutes',
            'value' => ['minutes' => 2880],
            'effective_from' => '2026-08-01',
            'status' => LegalParameter::STATUS_ACTIVE,
        ]);
        $calculations = collect(range(0, 5))->map(fn (int $offset): WorkDayCalculation => $this->calculatedWorkDay(
            $company,
            $relationship,
            '2027-01-0'.($offset + 4),
            480,
        ));

        app(ApplyOrdinaryOvertimeForDateRangeAction::class)->handle($company, '2027-01-04', '2027-01-09');

        $last = $calculations->last()->refresh();
        $this->assertSame(2760, $last->result_snapshot['ordinary_overtime']['weekly_limit_minutes']);
        $this->assertSame('company_parameter', $last->rules_snapshot['ordinary_overtime']['weekly_limit']['source']);
        $this->assertSame(2760, $last->rules_snapshot['ordinary_overtime']['weekly_limit']['legal_limit']['value']['minutes']);
    }

    public function test_overtime_bands_split_double_and_triple_minutes_by_week(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $calculations = collect(range(0, 6))->map(fn (int $offset): WorkDayCalculation => $this->calculatedWorkDay(
            $company,
            $relationship,
            '2026-08-0'.($offset + 3),
            600,
        ));

        app(ApplyOrdinaryOvertimeForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-09');

        $first = $calculations->first()->refresh();
        $fifth = $calculations->get(4)->refresh();
        $last = $calculations->last()->refresh();

        $this->assertSame(120, $first->overtime_minutes);
        $this->assertSame(120, $first->overtime_double_minutes);
        $this->assertSame(0, $first->overtime_triple_minutes);
        $this->assertSame(60, $fifth->overtime_double_minutes);
        $this->assertSame(60, $fifth->overtime_triple_minutes);
        $this->assertSame(0, $last->overtime_double_minutes);
        $this->assertSame(600, $last->overtime_triple_minutes);
        $this->assertSame(540, $calculations->sum(fn (WorkDayCalculation $calculation): int => $calculation->refresh()->overtime_double_minutes));
        $this->assertSame(780, $calculations->sum(fn (WorkDayCalculation $calculation): int => $calculation->refresh()->overtime_triple_minutes));
    }

    public function test_overtime_double_band_uses_the_2030_legal_version(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $calculations = collect(range(0, 5))->map(fn (int $offset): WorkDayCalculation => $this->calculatedWorkDay(
            $company,
            $relationship,
            CarbonImmutable::parse('2030-01-07')->addDays($offset)->toDateString(),
            600,
        ));

        app(ApplyOrdinaryOvertimeForDateRangeAction::class)->handle($company, '2030-01-07', '2030-01-12');

        $last = $calculations->last()->refresh();
        $this->assertSame(720, $calculations->sum(fn (WorkDayCalculation $calculation): int => $calculation->refresh()->overtime_double_minutes));
        $this->assertSame(720, $last->result_snapshot['ordinary_overtime']['weekly_double_overtime_limit_minutes']);
        $this->assertSame(5, $last->rules_snapshot['ordinary_overtime']['weekly_double_overtime_limit']['version']);
    }

    public function test_2026_and_2027_rest_hours_do_not_become_extra_when_weekly_extra_is_already_exhausted(): void
    {
        foreach ([['2026-08-03', 480, 0], ['2027-01-04', 480, 120], ['2027-01-04', 360, 0]] as [$start, $saturdayMinutes, $weeklyExtra]) {
            [$company, $relationship] = $this->relationshipFixture();
            $calculations = collect([600, 600, 600, 660, 480, $saturdayMinutes, 480])->map(fn (int $minutes, int $offset) => $this->calculatedWorkDay(
                $company, $relationship, CarbonImmutable::parse($start)->addDays($offset)->toDateString(), $minutes,
            ));
            $calculations[6]->workDay->update(['day_type' => 'rest']);
            $end = CarbonImmutable::parse($start)->addDays(6)->toDateString();
            app(ApplyOrdinaryOvertimeForDateRangeAction::class)->handle($company, $start, $end);
            $saturday = $calculations[5]->fresh();
            $sunday = $calculations[6]->fresh();
            $this->assertSame(0, data_get($saturday->result_snapshot, 'ordinary_overtime.daily_overtime_minutes'));
            $this->assertSame($weeklyExtra, data_get($saturday->result_snapshot, 'ordinary_overtime.weekly_overtime_minutes'));
            $this->assertSame(0, $sunday->overtime_minutes);
            $this->assertSame(480, $sunday->ordinary_minutes);
            $this->assertSame(0, data_get($sunday->result_snapshot, 'ordinary_overtime.weekly_base_minutes'));
            $this->assertSame(480, data_get($sunday->result_snapshot, 'ordinary_overtime.rest_work_minutes'));
            $this->assertSame(540 + $weeklyExtra, $calculations->sum(fn ($calculation) => $calculation->fresh()->overtime_minutes));
            $this->assertSame(540, $calculations->sum(fn ($calculation) => $calculation->fresh()->overtime_double_minutes));
            $this->assertSame($weeklyExtra, $calculations->sum(fn ($calculation) => $calculation->fresh()->overtime_triple_minutes));
            foreach ($calculations as $calculation) {
                $calculation->refresh();
                $this->assertSame($calculation->total_work_minutes, $calculation->ordinary_minutes + $calculation->overtime_minutes);
            }
            app(EvaluateWorkDayAlertsAction::class)->handle($company, $saturday->workDay->fresh());
            $this->assertFalse($saturday->workDay->alerts()->where('rule_code', 'daily_overtime_over_three_hours')->whereIn('status', Alert::OPEN_STATUSES)->exists());
        }
    }

    public function test_pending_classification_is_not_processed_as_overtime(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $calculation = $this->calculatedWorkDay($company, $relationship, '2026-08-03', 480, WorkDayCalculation::CLASSIFICATION_PENDING);

        $summary = app(ApplyOrdinaryOvertimeForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-03');

        $this->assertSame(['total' => 1, 'calculated' => 0, 'pending' => 1], $summary);
        $calculation->refresh();
        $this->assertSame(0, $calculation->ordinary_minutes);
        $this->assertSame(0, $calculation->overtime_minutes);
        $this->assertSame(0, $calculation->overtime_double_minutes);
        $this->assertSame(0, $calculation->overtime_triple_minutes);
        $this->assertArrayNotHasKey('ordinary_overtime', $calculation->result_snapshot);
    }

    /**
     * @return array{0: Company, 1: EmploymentRelationship}
     */
    private function relationshipFixture(): array
    {
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $center = Center::factory()->create(['company_id' => $company->id, 'timezone' => 'America/Mexico_City']);
        $worker = Worker::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $relationship = EmploymentRelationship::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $center->id,
            'started_at' => '2026-08-01',
            'status' => 'active',
        ]);

        return [$company, $relationship];
    }

    private function calculatedWorkDay(Company $company, EmploymentRelationship $relationship, string $date, int $minutes, string $classification = WorkDayCalculation::CLASSIFICATION_DIURNAL): WorkDayCalculation
    {
        $workDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $relationship->worker_id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $relationship->center_id,
            'work_date' => $date,
            'timezone' => 'America/Mexico_City',
            'status' => WorkDay::STATUS_CALCULATED,
        ]);
        $calculation = WorkDayCalculation::factory()->create([
            'company_id' => $company->id,
            'work_day_id' => $workDay->id,
            'total_work_minutes' => $minutes,
            'ordinary_minutes' => $minutes,
            'overtime_minutes' => 0,
            'classification' => $classification,
            'rules_snapshot' => [
                'schema_version' => 1,
                'legal_engine_applied' => $classification !== WorkDayCalculation::CLASSIFICATION_PENDING,
            ],
            'result_snapshot' => [
                'schema_version' => 1,
                'work_intervals' => [
                    ['start_utc' => "{$date} 14:00:00", 'end_utc' => "{$date} 22:00:00", 'minutes' => $minutes],
                ],
                'break_intervals' => [],
                'issues' => [],
                'legal_classification' => [
                    'classification' => $classification,
                    'daily_limit_minutes' => 480,
                ],
            ],
        ]);
        $workDay->forceFill(['active_calculation_id' => $calculation->id])->save();

        return $calculation;
    }
}
