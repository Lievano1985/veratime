<?php

namespace Tests\Feature\LegalRules;

use App\Domains\LegalRules\Actions\ApplyOrdinaryOvertimeForDateRangeAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\LegalParameter;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
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

    public function test_weekly_limit_moves_remaining_minutes_to_overtime(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $calculations = collect(range(0, 6))->map(fn (int $offset): WorkDayCalculation => $this->calculatedWorkDay(
            $company,
            $relationship,
            "2026-08-0".($offset + 3),
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
    }

    public function test_overtime_bands_split_double_and_triple_minutes_by_week(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $calculations = collect(range(0, 6))->map(fn (int $offset): WorkDayCalculation => $this->calculatedWorkDay(
            $company,
            $relationship,
            "2026-08-0".($offset + 3),
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
