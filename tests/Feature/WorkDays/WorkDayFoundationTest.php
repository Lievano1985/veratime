<?php

namespace Tests\Feature\WorkDays;

use App\Domains\WorkDays\Actions\RefreshWorkDaysForDateRangeAction;
use App\Domains\WorkDays\Actions\ResolveWorkDayForRelationshipDateAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use App\Models\TimeEvent;
use App\Models\WorkDay;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkDayFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_creates_expected_work_day_from_published_schedule_without_events(): void
    {
        [$company, $relationship, $assignment] = $this->publishedShiftAssignment();

        $result = app(RefreshWorkDaysForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-03');
        $workDay = app(ResolveWorkDayForRelationshipDateAction::class)->handle($company, $relationship, '2026-08-03');

        $this->assertSame(['scheduled' => 1, 'unscheduled' => 0, 'total' => 1], $result);
        $this->assertNotNull($workDay);
        $this->assertSame($assignment->id, $workDay->daily_schedule_assignment_id);
        $this->assertSame(WorkDay::STATUS_PENDING, $workDay->status);
        $this->assertSame(WorkDay::SCHEDULE_STATUS_SCHEDULED, $workDay->schedule_status);
        $this->assertSame('shift', $workDay->day_type);
        $this->assertSame(480, $workDay->expected_work_minutes);
        $this->assertSame(0, $workDay->valid_time_event_count);
        $this->assertSame([], $workDay->valid_time_event_ids);
    }

    public function test_refresh_updates_scheduled_work_day_with_valid_events_and_excludes_voided_events(): void
    {
        [$company, $relationship] = $this->publishedShiftAssignment();
        $valid = $this->timeEvent($company, $relationship, 'clock_in', '08:00:00');
        $this->timeEvent($company, $relationship, 'clock_out', '17:00:00', ['status' => 'voided', 'voided_at' => '2026-08-03 23:10:00']);

        app(RefreshWorkDaysForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-03');
        $workDay = app(ResolveWorkDayForRelationshipDateAction::class)->handle($company, $relationship, '2026-08-03');

        $this->assertSame(WorkDay::SCHEDULE_STATUS_SCHEDULED, $workDay->schedule_status);
        $this->assertSame(1, $workDay->valid_time_event_count);
        $this->assertSame([$valid->id], $workDay->valid_time_event_ids);
        $this->assertSame('2026-08-03 14:00:00', $workDay->first_event_at_utc->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-03 14:00:00', $workDay->last_event_at_utc->utc()->format('Y-m-d H:i:s'));
    }

    public function test_refresh_creates_unscheduled_work_day_for_valid_events_without_published_schedule(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        $event = $this->timeEvent($company, $relationship, 'clock_in', '07:55:00');

        $result = app(RefreshWorkDaysForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-03');
        $workDay = app(ResolveWorkDayForRelationshipDateAction::class)->handle($company, $relationship, '2026-08-03');

        $this->assertSame(['scheduled' => 0, 'unscheduled' => 1, 'total' => 1], $result);
        $this->assertSame(WorkDay::SCHEDULE_STATUS_UNSCHEDULED, $workDay->schedule_status);
        $this->assertNull($workDay->daily_schedule_assignment_id);
        $this->assertNull($workDay->schedule_batch_id);
        $this->assertNull($workDay->day_type);
        $this->assertSame(1, $workDay->valid_time_event_count);
        $this->assertSame([$event->id], $workDay->valid_time_event_ids);
        $this->assertSame('valid_events_without_published_schedule', $workDay->metadata['reason']);
    }

    public function test_refresh_is_tenant_scoped_and_does_not_create_future_tables(): void
    {
        [$company, $relationship] = $this->relationshipFixture();
        [$otherCompany, $otherRelationship] = $this->relationshipFixture();
        $this->timeEvent($company, $relationship, 'clock_in', '08:00:00');
        $this->timeEvent($otherCompany, $otherRelationship, 'clock_in', '08:00:00');

        app(RefreshWorkDaysForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-03');

        $this->assertSame(1, WorkDay::query()->where('company_id', $company->id)->count());
        $this->assertSame(0, WorkDay::query()->where('company_id', $otherCompany->id)->count());
        $this->assertTrue(Schema::hasTable('work_days'));
        $this->assertFalse(Schema::hasTable('work_day_calculations'));
        $this->assertFalse(Schema::hasTable('alerts'));
        $this->assertFalse(Schema::hasTable('incidents'));
        $this->assertFalse(Schema::hasTable('reports'));
    }

    /**
     * @return array{0: Company, 1: EmploymentRelationship, 2: DailyScheduleAssignment}
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
            'snapshot_sha256' => str_repeat('a', 64),
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

        return [$company, $relationship, $assignment->refresh()];
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

        return [$company, $relationship, $center];
    }

    private function timeEvent(Company $company, EmploymentRelationship $relationship, string $eventType, string $localTime, array $overrides = []): TimeEvent
    {
        return TimeEvent::factory()->create(array_replace([
            'company_id' => $company->id,
            'worker_id' => $relationship->worker_id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $relationship->center_id,
            'event_type' => $eventType,
            'occurred_local_date' => '2026-08-03',
            'occurred_local_time' => $localTime,
            'occurred_at_utc' => '2026-08-03 '.($localTime === '07:55:00' ? '13:55:00' : '14:00:00'),
            'timezone' => 'America/Mexico_City',
            'received_at' => '2026-08-03 14:01:00',
            'source' => 'web',
            'status' => 'valid',
            'metadata' => [],
        ], $overrides));
    }
}
