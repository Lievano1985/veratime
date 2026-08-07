<?php

namespace Tests\Feature\WorkDays;

use App\Domains\TimeRecords\Actions\ApproveManualTimeEventAction;
use App\Domains\TimeRecords\Actions\CreateTimeEventAction;
use App\Domains\TimeRecords\Actions\VoidTimeEventAction;
use App\Domains\WorkDays\Actions\ProcessSingleWorkDayAction;
use App\Domains\WorkDays\Jobs\RecalculateWorkDayFromTimeEventJob;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\ScheduleBatch;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use App\Support\RoleKey;
use Database\Seeders\AlertTypeSeeder;
use Database\Seeders\LegalRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecalculateWorkDayFromTimeEventJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LegalRuleSeeder::class);
        $this->seed(AlertTypeSeeder::class);
    }

    public function test_valid_clock_out_dispatches_recalculation_job_but_clock_in_does_not(): void
    {
        Queue::fake();
        [$company, $relationship] = $this->relationshipFixture();
        $worker = $relationship->worker;
        $action = app(CreateTimeEventAction::class);

        $action->handle($company, $worker, [
            'event_type' => 'clock_in',
            'occurred_local_date' => '2026-08-03',
            'occurred_local_time' => '08:00:00',
            'timezone' => 'America/Mexico_City',
            'received_at' => '2026-08-03 14:00:05',
            'source' => 'web',
            'status' => 'valid',
        ], $relationship, $relationship->center);

        Queue::assertNotPushed(RecalculateWorkDayFromTimeEventJob::class);

        $clockOut = $action->handle($company, $worker, [
            'event_type' => 'clock_out',
            'occurred_local_date' => '2026-08-03',
            'occurred_local_time' => '17:00:00',
            'timezone' => 'America/Mexico_City',
            'received_at' => '2026-08-03 23:00:05',
            'source' => 'web',
            'status' => 'valid',
        ], $relationship, $relationship->center);

        Queue::assertPushed(
            RecalculateWorkDayFromTimeEventJob::class,
            fn (RecalculateWorkDayFromTimeEventJob $job): bool => $job->timeEventId === $clockOut->id
                && $job->trigger === 'time_event_created',
        );
    }

    public function test_manual_approval_and_void_dispatch_recalculation_job(): void
    {
        Queue::fake();
        [$company, $relationship] = $this->relationshipFixture();
        $actor = $this->managerForCompany($company);
        $event = TimeEvent::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $relationship->worker_id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $relationship->center_id,
            'event_type' => 'clock_in',
            'occurred_local_date' => '2026-08-03',
            'occurred_local_time' => '08:00:00',
            'occurred_at_utc' => '2026-08-03 14:00:00',
            'timezone' => 'America/Mexico_City',
            'received_at' => '2026-08-03 14:01:00',
            'source' => 'admin_manual',
            'status' => 'pending_review',
        ]);

        app(ApproveManualTimeEventAction::class)->handle($event, $actor);

        Queue::assertPushed(
            RecalculateWorkDayFromTimeEventJob::class,
            fn (RecalculateWorkDayFromTimeEventJob $job): bool => $job->timeEventId === $event->id
                && $job->trigger === 'manual_approval',
        );

        app(VoidTimeEventAction::class)->handle($event->refresh(), $actor, 'Captura duplicada');

        Queue::assertPushed(
            RecalculateWorkDayFromTimeEventJob::class,
            fn (RecalculateWorkDayFromTimeEventJob $job): bool => $job->timeEventId === $event->id
                && $job->trigger === 'void',
        );
    }

    public function test_job_recalculates_previous_work_date_for_overnight_clock_out(): void
    {
        [$company, $relationship] = $this->publishedShiftAssignment('2026-08-03');
        $this->timeEvent($company, $relationship, 'clock_in', '2026-08-03', '22:00:00', '2026-08-04 04:00:00');
        $clockOut = $this->timeEvent($company, $relationship, 'clock_out', '2026-08-04', '06:00:00', '2026-08-04 12:00:00');

        $job = new RecalculateWorkDayFromTimeEventJob($clockOut->id, 'time_event_created');
        $job->handle(app(ProcessSingleWorkDayAction::class));

        $workDay = WorkDay::query()
            ->where('company_id', $company->id)
            ->where('employment_relationship_id', $relationship->id)
            ->whereDate('work_date', '2026-08-03')
            ->firstOrFail();

        $this->assertSame(WorkDay::STATUS_WITH_ALERTS, $workDay->status);
        $this->assertSame(2, $workDay->valid_time_event_count);
        $this->assertSame(480, $workDay->activeCalculation->total_work_minutes);
        $this->assertSame(WorkDayCalculation::GENERATED_BY_JOB, $workDay->activeCalculation->generated_by_type);
        $this->assertSame(1, $workDay->alerts()->where('rule_code', 'overtime_detected')->count());
        $this->assertSame(0, WorkDay::query()->where('company_id', $company->id)->whereDate('work_date', '2026-08-04')->count());
    }

    /**
     * @return array{0: Company, 1: EmploymentRelationship}
     */
    private function publishedShiftAssignment(string $workDate): array
    {
        [$company, $relationship, $center] = $this->relationshipFixture();
        $batch = ScheduleBatch::factory()->create([
            'company_id' => $company->id,
            'center_id' => $center->id,
            'period_start' => '2026-08-03',
            'period_end' => '2026-08-09',
            'version' => 1,
            'status' => 'published',
            'snapshot_sha256' => str_repeat('d', 64),
        ]);
        $assignment = DailyScheduleAssignment::factory()->create([
            'company_id' => $company->id,
            'schedule_batch_id' => $batch->id,
            'employment_relationship_id' => $relationship->id,
            'work_date' => $workDate,
            'day_type' => 'shift',
            'timezone' => 'America/Mexico_City',
        ]);
        DailyScheduleSegment::factory()->create([
            'company_id' => $company->id,
            'daily_schedule_assignment_id' => $assignment->id,
            'segment_type' => 'work',
            'duration_minutes' => 480,
        ]);

        return [$company->refresh(), $relationship->refresh()];
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

        return [$company->refresh(), $relationship->refresh()->load(['worker', 'center']), $center];
    }

    private function managerForCompany(Company $company): User
    {
        $role = Role::query()->firstOrCreate(
            ['key' => RoleKey::RH],
            ['name' => 'RH', 'description' => null, 'is_system' => true],
        );
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return $user;
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
