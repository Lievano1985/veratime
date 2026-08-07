<?php

namespace Tests\Feature\WorkDays;

use App\Domains\WorkDays\Actions\ProcessSingleWorkDayAction;
use App\Models\Alert;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use App\Models\TimeEvent;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use Database\Seeders\AlertTypeSeeder;
use Database\Seeders\LegalRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProcessSingleWorkDayActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LegalRuleSeeder::class);
        $this->seed(AlertTypeSeeder::class);
    }

    public function test_processes_scheduled_relationship_date_with_legal_calculation_and_alerts(): void
    {
        [$company, $relationship] = $this->publishedShiftAssignment();
        $this->timeEvent($company, $relationship, 'clock_in', '2026-08-03', '08:00:00', '2026-08-03 14:00:00');
        $this->timeEvent($company, $relationship, 'clock_out', '2026-08-03', '18:00:00', '2026-08-04 00:00:00');

        $summary = app(ProcessSingleWorkDayAction::class)->handle($company, $relationship, '2026-08-03', reason: 'Salida registrada');

        $workDay = WorkDay::query()->where('company_id', $company->id)->firstOrFail();
        $calculation = $workDay->activeCalculation;

        $this->assertSame(1, $summary['scheduled']);
        $this->assertSame(1, $summary['calculated']);
        $this->assertSame($workDay->id, $summary['work_day_id']);
        $this->assertSame(WorkDay::STATUS_WITH_ALERTS, $workDay->status);
        $this->assertSame(WorkDay::SCHEDULE_STATUS_SCHEDULED, $workDay->schedule_status);
        $this->assertSame(600, $calculation->total_work_minutes);
        $this->assertSame(480, $calculation->ordinary_minutes);
        $this->assertSame(120, $calculation->overtime_minutes);
        $this->assertSame(WorkDayCalculation::CLASSIFICATION_DIURNAL, $calculation->classification);
        $this->assertSame(1, Alert::query()->where('company_id', $company->id)->where('rule_code', 'overtime_detected')->count());
    }

    public function test_processes_unscheduled_relationship_date_from_valid_events(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $this->timeEvent($company, $relationship, 'clock_in', '2026-08-04', '08:00:00', '2026-08-04 14:00:00');
        $this->timeEvent($company, $relationship, 'clock_out', '2026-08-04', '16:00:00', '2026-08-04 22:00:00');

        $summary = app(ProcessSingleWorkDayAction::class)->handle($company, $relationship, '2026-08-04');

        $workDay = WorkDay::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame(1, $summary['unscheduled']);
        $this->assertSame(1, $summary['calculated']);
        $this->assertSame(WorkDay::SCHEDULE_STATUS_UNSCHEDULED, $workDay->schedule_status);
        $this->assertSame(WorkDay::STATUS_CALCULATED, $workDay->status);
        $this->assertSame(480, $workDay->activeCalculation->total_work_minutes);
    }

    public function test_blocks_relationship_from_other_company(): void
    {
        [$company] = $this->relationshipFixture();
        [$otherCompany, $otherRelationship] = $this->relationshipFixture();

        try {
            app(ProcessSingleWorkDayAction::class)->handle($company, $otherRelationship, '2026-08-03');
            $this->fail('Expected invalid relationship scope exception.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->assertSame(0, WorkDay::query()->where('company_id', $company->id)->count());
        $this->assertSame(0, WorkDay::query()->where('company_id', $otherCompany->id)->count());
    }

    /**
     * @return array{0: Company, 1: EmploymentRelationship, 2: Center}
     */
    private function publishedShiftAssignment(): array
    {
        [$company, $relationship, $center] = $this->relationshipFixture();
        $batch = ScheduleBatch::factory()->create([
            'company_id' => $company->id,
            'center_id' => $center->id,
            'period_start' => '2026-08-03',
            'period_end' => '2026-08-09',
            'version' => 1,
            'status' => 'published',
            'snapshot_sha256' => str_repeat('c', 64),
        ]);
        $assignment = DailyScheduleAssignment::factory()->create([
            'company_id' => $company->id,
            'schedule_batch_id' => $batch->id,
            'employment_relationship_id' => $relationship->id,
            'work_date' => '2026-08-03',
            'day_type' => 'shift',
            'timezone' => 'America/Mexico_City',
        ]);
        DailyScheduleSegment::factory()->create([
            'company_id' => $company->id,
            'daily_schedule_assignment_id' => $assignment->id,
            'segment_order' => 1,
            'segment_type' => 'work',
            'duration_minutes' => 480,
        ]);

        return [$company->refresh(), $relationship->refresh(), $center];
    }

    /**
     * @return array{0: Company, 1: EmploymentRelationship, 2: Center}
     */
    private function relationshipFixture(): array
    {
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $company->setting()->create(array_replace(Company::defaultSettings(), [
            'default_timezone' => 'America/Mexico_City',
        ]));
        $center = Center::factory()->create(['company_id' => $company->id, 'timezone' => 'America/Mexico_City']);
        $worker = Worker::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $relationship = EmploymentRelationship::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $center->id,
            'started_at' => '2026-08-01',
            'ended_at' => null,
            'status' => 'active',
        ]);

        return [$company->refresh(), $relationship->refresh(), $center];
    }

    private function timeEvent(Company $company, EmploymentRelationship $relationship, string $eventType, string $localDate, string $localTime, string $occurredAtUtc): TimeEvent
    {
        return TimeEvent::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $relationship->worker_id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $relationship->center_id,
            'event_type' => $eventType,
            'occurred_local_date' => $localDate,
            'occurred_local_time' => $localTime,
            'occurred_at_utc' => $occurredAtUtc,
            'timezone' => 'America/Mexico_City',
            'received_at' => $occurredAtUtc,
            'source' => 'web',
            'status' => 'valid',
            'metadata' => [],
        ]);
    }
}
