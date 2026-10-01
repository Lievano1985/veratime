<?php

namespace Tests\Feature\Personal;

use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyDayScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductSeeder::class);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-21 08:00:00', 'America/Mexico_City'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_linked_worker_sees_only_own_published_schedule_on_the_web(): void
    {
        [$company, $user, $relationship, $center] = $this->linkedWorkerContext();
        $otherWorker = Worker::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $otherRelationship = EmploymentRelationship::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $otherWorker->id,
            'center_id' => $center->id,
            'status' => 'active',
        ]);
        $publishedBatch = ScheduleBatch::factory()->create([
            'company_id' => $company->id,
            'center_id' => $center->id,
            'status' => 'published',
        ]);
        $ownSchedule = DailyScheduleAssignment::factory()->create([
            'company_id' => $company->id,
            'schedule_batch_id' => $publishedBatch->id,
            'employment_relationship_id' => $relationship->id,
            'work_date' => '2026-09-21',
            'day_type' => 'shift',
            'required_minutes' => 480,
        ]);
        DailyScheduleSegment::factory()->create([
            'company_id' => $company->id,
            'daily_schedule_assignment_id' => $ownSchedule->id,
            'segment_type' => 'work',
            'start_local_time' => '08:00:00',
            'end_local_time' => '16:00:00',
        ]);
        $otherSchedule = DailyScheduleAssignment::factory()->create([
            'company_id' => $company->id,
            'schedule_batch_id' => $publishedBatch->id,
            'employment_relationship_id' => $otherRelationship->id,
            'work_date' => '2026-09-21',
            'day_type' => 'shift',
        ]);
        DailyScheduleSegment::factory()->create([
            'company_id' => $company->id,
            'daily_schedule_assignment_id' => $otherSchedule->id,
            'segment_type' => 'work',
            'start_local_time' => '09:00:00',
            'end_local_time' => '17:00:00',
        ]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('personal.my-day'))
            ->assertOk()
            ->assertSee('Mi horario publicado')
            ->assertSee('Semana del 21/09 al 27/09')
            ->assertSee('Semana del 28/09 al 04/10')
            ->assertSee('Turno programado')
            ->assertSee('shift-turno')
            ->assertSee('08:00 - 16:00')
            ->assertSee('8 h')
            ->assertDontSee('09:00 - 17:00');
    }

    public function test_linked_worker_does_not_see_draft_schedule_on_the_web(): void
    {
        [$company, $user, $relationship, $center] = $this->linkedWorkerContext();
        $draftBatch = ScheduleBatch::factory()->create([
            'company_id' => $company->id,
            'center_id' => $center->id,
            'status' => 'draft',
        ]);
        $draftSchedule = DailyScheduleAssignment::factory()->create([
            'company_id' => $company->id,
            'schedule_batch_id' => $draftBatch->id,
            'employment_relationship_id' => $relationship->id,
            'work_date' => '2026-09-21',
            'day_type' => 'shift',
        ]);
        DailyScheduleSegment::factory()->create([
            'company_id' => $company->id,
            'daily_schedule_assignment_id' => $draftSchedule->id,
            'segment_type' => 'work',
            'start_local_time' => '10:00:00',
            'end_local_time' => '18:00:00',
        ]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get(route('personal.my-day'))
            ->assertOk()
            ->assertSee('Mi horario publicado')
            ->assertSee('No tienes horarios publicados para esta semana ni la siguiente.')
            ->assertDontSee('10:00 - 18:00');
    }

    /**
     * @return array{0: Company, 1: User, 2: EmploymentRelationship, 3: Center}
     */
    private function linkedWorkerContext(): array
    {
        $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
        $user = User::factory()->create(['status' => 'active']);
        $worker = Worker::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $center = Center::factory()->create(['company_id' => $company->id]);
        $relationship = EmploymentRelationship::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $center->id,
            'status' => 'active',
        ]);
        $user->companies()->attach($company, ['status' => 'active', 'is_default' => true]);
        UserWorkerLink::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'worker_id' => $worker->id,
            'status' => 'active',
        ]);

        return [$company, $user, $relationship, $center];
    }
}
