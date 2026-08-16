<?php

namespace Tests\Feature\WorkDays;

use App\Domains\TimeRecords\Actions\ResolveValidTimeEventsForWorkDateAction;
use App\Domains\WorkDays\Actions\CalculateWorkDayAction;
use App\Domains\WorkDays\Actions\CalculateWorkDaysForDateRangeAction;
use App\Domains\WorkDays\Actions\RefreshWorkDaysForDateRangeAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WorkDayCalculationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_clock_in_out_creates_active_calculation_and_marks_work_day_calculated(): void
    {
        [$company, $relationship, $workDay] = $this->workDayFixture();
        $this->timeEvent($company, $relationship, 'clock_in', '08:00:00', '2026-08-03 14:00:00');
        $this->timeEvent($company, $relationship, 'clock_out', '17:00:00', '2026-08-03 23:00:00');

        $calculation = app(CalculateWorkDayAction::class)->handle($company, $workDay, reason: 'Prueba automatizada');

        $this->assertNotNull($calculation);
        $this->assertSame(1, $calculation->version);
        $this->assertSame(WorkDayCalculation::STATUS_ACTIVE, $calculation->status);
        $this->assertSame(540, $calculation->total_work_minutes);
        $this->assertSame(540, $calculation->ordinary_minutes);
        $this->assertSame(0, $calculation->overtime_minutes);
        $this->assertFalse($calculation->rules_snapshot['legal_engine_applied']);
        $this->assertSame(WorkDay::STATUS_CALCULATED, $workDay->refresh()->status);
        $this->assertSame($calculation->id, $workDay->active_calculation_id);
    }

    public function test_break_minutes_are_subtracted_from_operational_total(): void
    {
        [$company, $relationship, $workDay] = $this->workDayFixture();
        $this->timeEvent($company, $relationship, 'clock_in', '08:00:00', '2026-08-03 14:00:00');
        $this->timeEvent($company, $relationship, 'break_start', '12:00:00', '2026-08-03 18:00:00');
        $this->timeEvent($company, $relationship, 'break_end', '12:30:00', '2026-08-03 18:30:00');
        $this->timeEvent($company, $relationship, 'clock_out', '17:00:00', '2026-08-03 23:00:00');

        $calculation = app(CalculateWorkDayAction::class)->handle($company, $workDay);

        $this->assertSame(510, $calculation->total_work_minutes);
        $this->assertSame(30, $calculation->break_minutes);
        $this->assertSame(WorkDay::STATUS_CALCULATED, $workDay->refresh()->status);
    }

    public function test_incomplete_event_sequence_is_kept_under_review_without_losing_events(): void
    {
        [$company, $relationship, $workDay] = $this->workDayFixture();
        $event = $this->timeEvent($company, $relationship, 'clock_in', '08:00:00', '2026-08-03 14:00:00');

        $calculation = app(CalculateWorkDayAction::class)->handle($company, $workDay);

        $this->assertSame(WorkDay::STATUS_UNDER_REVIEW, $workDay->refresh()->status);
        $this->assertSame(0, $calculation->total_work_minutes);
        $this->assertSame(['missing_clock_out'], $calculation->result_snapshot['issues']);
        $this->assertDatabaseHas('time_events', [
            'id' => $event->id,
            'status' => 'valid',
        ]);
    }

    public function test_recalculation_supersedes_previous_active_version(): void
    {
        [$company, $relationship, $workDay] = $this->workDayFixture();
        $this->timeEvent($company, $relationship, 'clock_in', '08:00:00', '2026-08-03 14:00:00');
        $oldOut = $this->timeEvent($company, $relationship, 'clock_out', '12:00:00', '2026-08-03 18:00:00');

        $first = app(CalculateWorkDayAction::class)->handle($company, $workDay);
        $oldOut->forceFill(['status' => 'voided', 'voided_at' => '2026-08-03 18:30:00'])->save();
        $this->timeEvent($company, $relationship, 'clock_out', '17:00:00', '2026-08-03 23:00:00');

        $second = app(CalculateWorkDayAction::class)->handle($company, $workDay);

        $this->assertSame(1, $first->version);
        $this->assertSame(WorkDayCalculation::STATUS_SUPERSEDED, $first->refresh()->status);
        $this->assertSame(2, $second->version);
        $this->assertSame(540, $second->total_work_minutes);
        $this->assertSame($second->id, $workDay->refresh()->active_calculation_id);
    }

    public function test_out_of_order_insertions_are_resolved_by_occurred_at(): void
    {
        [$company, $relationship, $workDay] = $this->workDayFixture();
        $this->timeEvent($company, $relationship, 'clock_out', '17:00:00', '2026-08-03 23:00:00');
        $this->timeEvent($company, $relationship, 'clock_in', '08:00:00', '2026-08-03 14:00:00');

        $calculation = app(CalculateWorkDayAction::class)->handle($company, $workDay);

        $this->assertSame(540, $calculation->total_work_minutes);
        $this->assertSame([], $calculation->result_snapshot['issues']);
    }

    public function test_overnight_unscheduled_events_belong_to_clock_in_work_date(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $this->timeEventOnDate($company, $relationship, 'clock_in', '2026-08-17', '22:00:00', '2026-08-18 04:00:00');
        $this->timeEventOnDate($company, $relationship, 'break_start', '2026-08-18', '02:00:00', '2026-08-18 08:00:00');
        $this->timeEventOnDate($company, $relationship, 'break_end', '2026-08-18', '02:30:00', '2026-08-18 08:30:00');
        $this->timeEventOnDate($company, $relationship, 'clock_out', '2026-08-18', '06:00:00', '2026-08-18 12:00:00');
        WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $relationship->worker_id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $relationship->center_id,
            'work_date' => '2026-08-18',
            'timezone' => 'America/Mexico_City',
            'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_UNSCHEDULED,
            'day_type' => null,
            'expected_work_minutes' => null,
            'valid_time_event_count' => 3,
            'metadata' => [
                'schema_version' => 1,
                'source' => 'time_events',
                'reason' => 'valid_events_without_published_schedule',
            ],
        ]);

        app(RefreshWorkDaysForDateRangeAction::class)->handle($company, '2026-08-17', '2026-08-18');
        app(CalculateWorkDaysForDateRangeAction::class)->handle($company, '2026-08-17', '2026-08-18');

        $workDay = WorkDay::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $relationship->worker_id)
            ->whereDate('work_date', '2026-08-17')
            ->firstOrFail();

        $this->assertSame(1, WorkDay::query()->where('company_id', $company->id)->count());
        $this->assertSame(WorkDay::STATUS_CALCULATED, $workDay->status);
        $this->assertSame(4, $workDay->valid_time_event_count);
        $this->assertCount(4, $workDay->valid_time_event_ids);
        $this->assertSame(450, $workDay->activeCalculation->total_work_minutes);
        $this->assertSame(30, $workDay->activeCalculation->break_minutes);
        $this->assertCount(0, app(ResolveValidTimeEventsForWorkDateAction::class)->handle($company, $relationship, '2026-08-18'));
    }

    public function test_consecutive_overnight_shifts_do_not_mix_previous_morning_events_with_next_clock_in(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $this->timeEventOnDate($company, $relationship, 'clock_in', '2026-08-17', '22:00:00', '2026-08-18 04:00:00');
        $this->timeEventOnDate($company, $relationship, 'break_start', '2026-08-18', '02:00:00', '2026-08-18 08:00:00');
        $this->timeEventOnDate($company, $relationship, 'break_end', '2026-08-18', '02:30:00', '2026-08-18 08:30:00');
        $this->timeEventOnDate($company, $relationship, 'clock_out', '2026-08-18', '06:00:00', '2026-08-18 12:00:00');
        $this->timeEventOnDate($company, $relationship, 'clock_in', '2026-08-18', '22:00:00', '2026-08-19 04:00:00');
        $this->timeEventOnDate($company, $relationship, 'break_start', '2026-08-19', '02:00:00', '2026-08-19 08:00:00');
        $this->timeEventOnDate($company, $relationship, 'break_end', '2026-08-19', '02:30:00', '2026-08-19 08:30:00');
        $this->timeEventOnDate($company, $relationship, 'clock_out', '2026-08-19', '06:00:00', '2026-08-19 12:00:00');

        app(RefreshWorkDaysForDateRangeAction::class)->handle($company, '2026-08-17', '2026-08-19');
        $summary = app(CalculateWorkDaysForDateRangeAction::class)->handle($company, '2026-08-17', '2026-08-19');

        $firstWorkDay = WorkDay::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $relationship->worker_id)
            ->whereDate('work_date', '2026-08-17')
            ->firstOrFail();
        $secondWorkDay = WorkDay::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $relationship->worker_id)
            ->whereDate('work_date', '2026-08-18')
            ->firstOrFail();

        $this->assertSame(['total' => 2, 'calculated' => 2, 'under_review' => 0, 'skipped' => 0], $summary);
        $this->assertSame(2, WorkDay::query()->where('company_id', $company->id)->count());
        $this->assertSame(450, $firstWorkDay->activeCalculation->total_work_minutes);
        $this->assertSame(450, $secondWorkDay->activeCalculation->total_work_minutes);
        $this->assertSame([], $secondWorkDay->activeCalculation->result_snapshot['issues']);
        $this->assertSame(
            ['clock_in', 'break_start', 'break_end', 'clock_out'],
            collect($secondWorkDay->activeCalculation->inputs_snapshot['events'])->pluck('event_type')->all(),
        );
    }

    public function test_equal_timestamps_have_deterministic_event_type_order(): void
    {
        [$company, $relationship, $workDay] = $this->workDayFixture();
        $this->timeEvent($company, $relationship, 'clock_out', '08:00:00', '2026-08-03 14:00:00');
        $this->timeEvent($company, $relationship, 'clock_in', '08:00:00', '2026-08-03 14:00:00');

        $calculation = app(CalculateWorkDayAction::class)->handle($company, $workDay);

        $this->assertSame(0, $calculation->total_work_minutes);
        $this->assertSame([], $calculation->result_snapshot['issues']);
        $this->assertSame(['clock_in', 'clock_out'], collect($calculation->inputs_snapshot['events'])->pluck('event_type')->all());
    }

    public function test_range_calculation_is_tenant_scoped_and_does_not_create_future_modules(): void
    {
        [$company, $relationship, $workDay] = $this->workDayFixture();
        [$otherCompany, $otherRelationship] = $this->workDayFixture();
        $this->timeEvent($company, $relationship, 'clock_in', '08:00:00', '2026-08-03 14:00:00');
        $this->timeEvent($company, $relationship, 'clock_out', '17:00:00', '2026-08-03 23:00:00');
        $this->timeEvent($otherCompany, $otherRelationship, 'clock_in', '08:00:00', '2026-08-03 14:00:00');
        $this->timeEvent($otherCompany, $otherRelationship, 'clock_out', '17:00:00', '2026-08-03 23:00:00');

        $summary = app(CalculateWorkDaysForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-03');

        $this->assertSame(['total' => 1, 'calculated' => 1, 'under_review' => 0, 'skipped' => 0], $summary);
        $this->assertSame(WorkDay::STATUS_CALCULATED, $workDay->refresh()->status);
        $this->assertSame(0, WorkDayCalculation::query()->where('company_id', $otherCompany->id)->count());
        $this->assertTrue(Schema::hasTable('alerts'));
        $this->assertFalse(Schema::hasTable('incidents'));
        $this->assertFalse(Schema::hasTable('reports'));
    }

    public function test_ui_can_run_manual_calculation_from_work_days(): void
    {
        [$company, $relationship] = $this->companyUserAndWorkDay();
        $this->timeEvent($company, $relationship, 'clock_in', '08:00:00', '2026-08-03 14:00:00');
        $this->timeEvent($company, $relationship, 'clock_out', '17:00:00', '2026-08-03 23:00:00');

        $this->actingAs($company->users()->first())->withSession(['current_company_id' => $company->id]);

        Volt::test('work-days.index')
            ->call('openProcessPanel')
            ->set('processForm.date_from', '2026-08-03')
            ->set('processForm.date_to', '2026-08-03')
            ->set('processForm.reason', 'Prueba UI')
            ->call('processWorkDays')
            ->assertHasNoErrors()
            ->assertSee('Recalculo de jornadas');

        $this->assertSame(1, WorkDayCalculation::query()->where('company_id', $company->id)->count());
    }

    /**
     * @return array{0: Company, 1: EmploymentRelationship, 2: WorkDay}
     */
    private function workDayFixture(): array
    {
        [$company, $relationship, $center] = $this->relationshipFixture();
        $worker = $relationship->worker;
        $workDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $center->id,
            'work_date' => '2026-08-03',
            'timezone' => 'America/Mexico_City',
            'status' => WorkDay::STATUS_PENDING,
            'valid_time_event_count' => 2,
        ]);

        return [$company, $relationship, $workDay];
    }

    /**
     * @return array{0: Company, 1: EmploymentRelationship, 2: Center}
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
            'ended_at' => null,
            'status' => 'active',
        ]);

        return [$company, $relationship->refresh(), $center];
    }

    /**
     * @return array{0: Company, 1: EmploymentRelationship}
     */
    private function companyUserAndWorkDay(): array
    {
        [$company, $relationship] = $this->workDayFixture();
        $role = Role::query()->firstOrCreate(
            ['key' => RoleKey::ADMIN_EMPRESA],
            ['name' => 'Administrador', 'description' => null, 'is_system' => true],
        );
        $user = User::factory()->create();
        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return [$company, $relationship];
    }

    private function timeEvent(Company $company, EmploymentRelationship $relationship, string $eventType, string $localTime, string $occurredAtUtc): TimeEvent
    {
        return $this->timeEventOnDate($company, $relationship, $eventType, '2026-08-03', $localTime, $occurredAtUtc);
    }

    private function timeEventOnDate(Company $company, EmploymentRelationship $relationship, string $eventType, string $localDate, string $localTime, string $occurredAtUtc): TimeEvent
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
            'received_at' => '2026-08-03 23:30:00',
            'source' => 'web',
            'status' => 'valid',
            'metadata' => [],
        ]);
    }
}
