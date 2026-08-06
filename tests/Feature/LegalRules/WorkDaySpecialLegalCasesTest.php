<?php

namespace Tests\Feature\LegalRules;

use App\Domains\LegalRules\Actions\ApplySpecialLegalCasesForDateRangeAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\MandatoryRestDay;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkDaySpecialLegalCasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sunday_worked_minutes_are_marked(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $calculation = $this->calculatedWorkDay($company, $relationship, '2026-08-09', 480);

        $summary = app(ApplySpecialLegalCasesForDateRangeAction::class)->handle($company, '2026-08-09', '2026-08-09');

        $calculation->refresh();
        $this->assertSame(1, $summary['calculated']);
        $this->assertSame(1, $summary['sunday']);
        $this->assertSame(480, $calculation->sunday_minutes);
        $this->assertSame(0, $calculation->mandatory_rest_minutes);
        $this->assertSame(480, $calculation->result_snapshot['special_legal_cases']['sunday_minutes']);
    }

    public function test_company_mandatory_rest_day_worked_minutes_are_marked(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        MandatoryRestDay::factory()->create([
            'company_id' => $company->id,
            'name' => 'Descanso interno',
            'date' => '2026-08-03',
            'type' => 'company_internal',
            'scope' => 'company',
            'status' => 'active',
        ]);
        $calculation = $this->calculatedWorkDay($company, $relationship, '2026-08-03', 420);

        $summary = app(ApplySpecialLegalCasesForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-03');

        $calculation->refresh();
        $this->assertSame(1, $summary['mandatory_rest']);
        $this->assertSame(420, $calculation->mandatory_rest_minutes);
        $this->assertSame('Descanso interno', $calculation->rules_snapshot['special_legal_cases']['mandatory_rest']['matches'][0]['name']);
    }

    public function test_national_mandatory_rest_day_applies_by_country_and_date(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        MandatoryRestDay::factory()->national()->create([
            'name' => 'Descanso nacional',
            'date' => '2026-08-03',
            'status' => 'active',
        ]);
        $calculation = $this->calculatedWorkDay($company, $relationship, '2026-08-03', 360);

        app(ApplySpecialLegalCasesForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-03');

        $calculation->refresh();
        $this->assertSame(360, $calculation->mandatory_rest_minutes);
        $this->assertSame('national', $calculation->rules_snapshot['special_legal_cases']['mandatory_rest']['matches'][0]['scope']);
    }

    public function test_week_without_rest_is_flagged_for_review_without_creating_alerts(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $calculations = collect(range(0, 6))->map(fn (int $offset): WorkDayCalculation => $this->calculatedWorkDay(
            $company,
            $relationship,
            '2026-08-0'.($offset + 3),
            480,
        ));

        $summary = app(ApplySpecialLegalCasesForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-09');

        $first = $calculations->first()->refresh();
        $this->assertSame(7, $summary['weekly_rest_review']);
        $this->assertTrue($first->result_snapshot['special_legal_cases']['weekly_rest']['requires_review']);
        $this->assertSame(7, $first->result_snapshot['special_legal_cases']['weekly_rest']['worked_days_in_week']);
    }

    public function test_pending_classification_is_not_processed_as_special_case(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $calculation = $this->calculatedWorkDay($company, $relationship, '2026-08-09', 480, WorkDayCalculation::CLASSIFICATION_PENDING);

        $summary = app(ApplySpecialLegalCasesForDateRangeAction::class)->handle($company, '2026-08-09', '2026-08-09');

        $calculation->refresh();
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(0, $calculation->sunday_minutes);
        $this->assertSame(0, $calculation->mandatory_rest_minutes);
        $this->assertArrayNotHasKey('special_legal_cases', $calculation->result_snapshot);
    }

    public function test_other_company_mandatory_rest_day_does_not_apply(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $otherCompany = Company::factory()->create(['status' => 'active']);
        MandatoryRestDay::factory()->create([
            'company_id' => $otherCompany->id,
            'date' => '2026-08-03',
            'type' => 'company_internal',
            'scope' => 'company',
            'status' => 'active',
        ]);
        $calculation = $this->calculatedWorkDay($company, $relationship, '2026-08-03', 480);

        $summary = app(ApplySpecialLegalCasesForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-03');

        $calculation->refresh();
        $this->assertSame(1, $summary['calculated']);
        $this->assertSame(0, $summary['mandatory_rest']);
        $this->assertSame(0, $calculation->mandatory_rest_minutes);
    }

    /**
     * @return array{0: Company, 1: EmploymentRelationship}
     */
    private function relationshipFixture(): array
    {
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $center = Center::factory()->create([
            'company_id' => $company->id,
            'timezone' => 'America/Mexico_City',
            'address' => ['country_code' => 'MX', 'jurisdiction_code' => 'MX-JAL'],
        ]);
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
