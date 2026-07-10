<?php

use App\Domains\Schedules\Actions\CreateScheduleAssignmentAction;
use App\Domains\Schedules\Actions\InactivateScheduleAssignmentAction;
use App\Domains\Schedules\Actions\ReplaceScheduleAssignmentAction;
use App\Domains\Schedules\Actions\ResolveScheduleForWorkerDateAction;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;

it('guest cannot access schedule assignments route', function (): void {
    $this->get(route('schedule-assignments.index'))
        ->assertRedirect(route('login'));
});

it('authenticated user without active company cannot access schedule assignments route', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('schedule-assignments.index'))
        ->assertForbidden();
});

it('inactive company blocks schedule assignment operations', function (): void {
    $company = Company::factory()->create(['status' => 'inactive']);
    $user = scheduleAssignmentUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedule-assignments.index')
        ->assertForbidden();
});

it('unauthorized role cannot manage schedule assignments', function (): void {
    $company = Company::factory()->create();
    $user = scheduleAssignmentUserWithCompany($company, 'supervisor');

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedule-assignments.index')
        ->assertForbidden();
});

it('user sees only assignments from active company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = scheduleAssignmentUserWithCompany($company);
    $visibleWorker = Worker::factory()->create([
        'company_id' => $company->id,
        'full_name' => 'Trabajador visible',
    ]);
    $foreignWorker = Worker::factory()->create([
        'company_id' => $otherCompany->id,
        'full_name' => 'Trabajador ajeno',
    ]);

    ScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $visibleWorker->id,
        'schedule_id' => Schedule::factory()->create(['company_id' => $company->id])->id,
        'employment_relationship_id' => null,
    ]);
    ScheduleAssignment::factory()->create([
        'company_id' => $otherCompany->id,
        'worker_id' => $foreignWorker->id,
        'schedule_id' => Schedule::factory()->create(['company_id' => $otherCompany->id])->id,
        'employment_relationship_id' => null,
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedule-assignments.index')
        ->assertSee('Trabajador visible')
        ->assertDontSee('Trabajador ajeno');
});

it('allows crossing midnight when flag is enabled', function (): void {
    $company = Company::factory()->create();
    $user = scheduleAssignmentUserWithCompany($company);
    $schedule = Schedule::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->call('loadEditForm', $schedule->id)
        ->set('daysForm.1.start_time', '22:00')
        ->set('daysForm.1.end_time', '06:00')
        ->set('daysForm.1.crosses_midnight', true)
        ->call('saveDays')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('schedule_days', [
        'company_id' => $company->id,
        'schedule_id' => $schedule->id,
        'day_of_week' => 1,
        'start_time' => '22:00',
        'end_time' => '06:00',
        'crosses_midnight' => true,
    ]);
});

it('rejects overnight times when crossing midnight is disabled', function (): void {
    $company = Company::factory()->create();
    $user = scheduleAssignmentUserWithCompany($company);
    $schedule = Schedule::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->call('loadEditForm', $schedule->id)
        ->set('daysForm.1.start_time', '22:00')
        ->set('daysForm.1.end_time', '06:00')
        ->set('daysForm.1.crosses_midnight', false)
        ->call('saveDays')
        ->assertHasErrors(['daysForm.1.crosses_midnight']);
});

it('allows normal same-day hours without crossing midnight', function (): void {
    $company = Company::factory()->create();
    $user = scheduleAssignmentUserWithCompany($company);
    $schedule = Schedule::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->call('loadEditForm', $schedule->id)
        ->set('daysForm.1.start_time', '08:00')
        ->set('daysForm.1.end_time', '17:00')
        ->set('daysForm.1.crosses_midnight', false)
        ->call('saveDays')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('schedule_days', [
        'schedule_id' => $schedule->id,
        'day_of_week' => 1,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'crosses_midnight' => false,
    ]);
});

it('allows non working days with null times', function (): void {
    $company = Company::factory()->create();
    $user = scheduleAssignmentUserWithCompany($company);
    $schedule = Schedule::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->call('loadEditForm', $schedule->id)
        ->set('daysForm.0.is_working_day', false)
        ->set('daysForm.0.start_time', '')
        ->set('daysForm.0.end_time', '')
        ->call('saveDays')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('schedule_days', [
        'schedule_id' => $schedule->id,
        'day_of_week' => 0,
        'is_working_day' => false,
        'start_time' => null,
        'end_time' => null,
    ]);
});

it('creates schedule assignment for a worker', function (): void {
    [$company, $user, $worker, $schedule, $relationship] = scheduleAssignmentFixture();

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedule-assignments.index')
        ->set('form.worker_id', $worker->id)
        ->set('form.schedule_id', $schedule->id)
        ->set('form.employment_relationship_id', $relationship->id)
        ->set('form.effective_from', '2026-08-01')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('schedule_assignments', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'schedule_id' => $schedule->id,
        'employment_relationship_id' => $relationship->id,
        'effective_from' => '2026-08-01 00:00:00',
        'status' => 'active',
    ]);
});

it('does not accept manipulated company id when creating assignment', function (): void {
    [$company, $user, $worker, $schedule] = scheduleAssignmentFixture();
    $otherCompany = Company::factory()->create();

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedule-assignments.index')
        ->set('form.worker_id', $worker->id)
        ->set('form.schedule_id', $schedule->id)
        ->set('form.company_id', $otherCompany->id)
        ->set('form.effective_from', '2026-08-01')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('schedule_assignments', [
        'company_id' => $otherCompany->id,
        'worker_id' => $worker->id,
    ]);
    $this->assertDatabaseHas('schedule_assignments', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
    ]);
});

it('cannot assign schedule from another company', function (): void {
    [$company, $user, $worker] = scheduleAssignmentFixture();
    $foreignSchedule = Schedule::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedule-assignments.index')
        ->set('form.worker_id', $worker->id)
        ->set('form.schedule_id', $foreignSchedule->id)
        ->set('form.effective_from', '2026-08-01')
        ->call('save')
        ->assertHasErrors(['form.schedule_id']);
});

it('cannot assign worker from another company', function (): void {
    [$company, $user, , $schedule] = scheduleAssignmentFixture();
    $foreignWorker = Worker::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedule-assignments.index')
        ->set('form.worker_id', $foreignWorker->id)
        ->set('form.schedule_id', $schedule->id)
        ->set('form.effective_from', '2026-08-01')
        ->call('save')
        ->assertHasErrors(['form.worker_id']);
});

it('employment relationship must belong to same worker and company', function (): void {
    [$company, , $worker, $schedule] = scheduleAssignmentFixture();
    $otherWorker = Worker::factory()->create(['company_id' => $company->id]);
    $otherRelationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $otherWorker->id,
    ]);

    $this->expectException(\InvalidArgumentException::class);

    app(ReplaceScheduleAssignmentAction::class)->handle($company, $worker, $schedule, $otherRelationship, [
        'effective_from' => '2026-08-01',
    ]);
});

it('resolves current schedule for worker and date', function (): void {
    [$company, , $worker, $schedule] = scheduleAssignmentFixture();

    ScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'schedule_id' => $schedule->id,
        'employment_relationship_id' => null,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
        'status' => 'active',
    ]);

    $assignment = app(ResolveScheduleForWorkerDateAction::class)->handle($company, $worker, '2026-08-10');

    expect($assignment)->not->toBeNull()
        ->and($assignment->schedule_id)->toBe($schedule->id);
});

it('replaces active assignment by closing previous and creating a new one', function (): void {
    [$company, , $worker, $oldSchedule] = scheduleAssignmentFixture();
    $newSchedule = Schedule::factory()->create(['company_id' => $company->id]);
    $oldAssignment = ScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'schedule_id' => $oldSchedule->id,
        'employment_relationship_id' => null,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
        'status' => 'active',
    ]);

    app(ReplaceScheduleAssignmentAction::class)->handle($company, $worker, $newSchedule, null, [
        'effective_from' => '2026-08-15 00:00:00',
    ]);

    $oldAssignment->refresh();

    expect($oldAssignment->status)->toBe('replaced')
        ->and($oldAssignment->effective_to->toDateString())->toBe('2026-08-14');

    $this->assertDatabaseHas('schedule_assignments', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'schedule_id' => $newSchedule->id,
        'effective_from' => '2026-08-15 00:00:00',
        'status' => 'active',
    ]);
});

it('does not allow overlapping active assignments', function (): void {
    [$company, , $worker, $schedule] = scheduleAssignmentFixture();
    $otherSchedule = Schedule::factory()->create(['company_id' => $company->id]);

    app(CreateScheduleAssignmentAction::class)->handle($company, $worker, $schedule, null, [
        'effective_from' => '2026-08-01',
        'effective_to' => '2026-08-31',
    ]);

    $this->expectException(\InvalidArgumentException::class);

    app(CreateScheduleAssignmentAction::class)->handle($company, $worker, $otherSchedule, null, [
        'effective_from' => '2026-08-15',
    ]);
});

it('inactivates assignment without deleting history', function (): void {
    [$company, , $worker, $schedule] = scheduleAssignmentFixture();
    $assignment = ScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'schedule_id' => $schedule->id,
        'employment_relationship_id' => null,
        'status' => 'active',
    ]);

    app(InactivateScheduleAssignmentAction::class)->handle($company, $assignment);

    $this->assertDatabaseHas('schedule_assignments', [
        'id' => $assignment->id,
        'status' => 'inactive',
    ]);
});

it('future assignment does not modify past resolution', function (): void {
    [$company, , $worker, $oldSchedule] = scheduleAssignmentFixture();
    $newSchedule = Schedule::factory()->create(['company_id' => $company->id]);

    app(ReplaceScheduleAssignmentAction::class)->handle($company, $worker, $oldSchedule, null, [
        'effective_from' => '2026-08-01',
    ]);
    app(ReplaceScheduleAssignmentAction::class)->handle($company, $worker, $newSchedule, null, [
        'effective_from' => '2026-08-15',
    ]);

    $resolver = app(ResolveScheduleForWorkerDateAction::class);

    expect($resolver->handle($company, $worker, '2026-08-10')->schedule_id)->toBe($oldSchedule->id)
        ->and($resolver->handle($company, $worker, '2026-08-16')->schedule_id)->toBe($newSchedule->id);
});

it('preserves assignment history when replacing', function (): void {
    [$company, , $worker, $oldSchedule] = scheduleAssignmentFixture();
    $newSchedule = Schedule::factory()->create(['company_id' => $company->id]);
    $oldAssignment = app(ReplaceScheduleAssignmentAction::class)->handle($company, $worker, $oldSchedule, null, [
        'effective_from' => '2026-08-01',
    ]);

    app(ReplaceScheduleAssignmentAction::class)->handle($company, $worker, $newSchedule, null, [
        'effective_from' => '2026-08-15',
    ]);

    $oldAssignment->refresh();

    expect($oldAssignment->schedule_id)->toBe($oldSchedule->id)
        ->and($oldAssignment->worker_id)->toBe($worker->id)
        ->and($oldAssignment->effective_from->toDateString())->toBe('2026-08-01');
});

it('creates assignment as active when inactive status is provided', function (): void {
    [$company, , $worker, $schedule] = scheduleAssignmentFixture();

    $assignment = app(CreateScheduleAssignmentAction::class)->handle($company, $worker, $schedule, null, [
        'effective_from' => '2026-08-01',
        'status' => 'inactive',
    ]);

    expect($assignment->status)->toBe('active');
    $this->assertDatabaseHas('schedule_assignments', [
        'id' => $assignment->id,
        'status' => 'active',
    ]);
});

it('creates assignment as active when replaced status is provided', function (): void {
    [$company, , $worker, $schedule] = scheduleAssignmentFixture();

    $assignment = app(CreateScheduleAssignmentAction::class)->handle($company, $worker, $schedule, null, [
        'effective_from' => '2026-08-01',
        'status' => 'replaced',
    ]);

    expect($assignment->status)->toBe('active');
    $this->assertDatabaseHas('schedule_assignments', [
        'id' => $assignment->id,
        'status' => 'active',
    ]);
});

it('blocks hard deleting a worker with schedule assignment history', function (): void {
    [$company, , $worker, $schedule] = scheduleAssignmentFixture();

    $assignment = app(CreateScheduleAssignmentAction::class)->handle($company, $worker, $schedule, null, [
        'effective_from' => '2026-08-01',
    ]);

    expect(fn () => $worker->delete())->toThrow(QueryException::class);

    $this->assertDatabaseHas('workers', ['id' => $worker->id]);
    $this->assertDatabaseHas('schedule_assignments', ['id' => $assignment->id]);
});

it('blocks hard deleting a schedule with assignment history', function (): void {
    [$company, , $worker, $schedule] = scheduleAssignmentFixture();

    $assignment = app(CreateScheduleAssignmentAction::class)->handle($company, $worker, $schedule, null, [
        'effective_from' => '2026-08-01',
    ]);

    expect(fn () => $schedule->delete())->toThrow(QueryException::class);

    $this->assertDatabaseHas('schedules', ['id' => $schedule->id]);
    $this->assertDatabaseHas('schedule_assignments', ['id' => $assignment->id]);
});

it('allows adjacent assignment after effective_to and rejects same day overlap', function (): void {
    [$company, , $worker, $schedule] = scheduleAssignmentFixture();
    $nextSchedule = Schedule::factory()->create(['company_id' => $company->id]);
    $overlapSchedule = Schedule::factory()->create(['company_id' => $company->id]);

    app(CreateScheduleAssignmentAction::class)->handle($company, $worker, $schedule, null, [
        'effective_from' => '2026-08-01',
        'effective_to' => '2026-08-14',
    ]);

    $nextAssignment = app(CreateScheduleAssignmentAction::class)->handle($company, $worker, $nextSchedule, null, [
        'effective_from' => '2026-08-15',
    ]);

    expect($nextAssignment->effective_from->toDateString())->toBe('2026-08-15');

    expect(fn () => app(CreateScheduleAssignmentAction::class)->handle($company, $worker, $overlapSchedule, null, [
        'effective_from' => '2026-08-14',
    ]))->toThrow(InvalidArgumentException::class);
});
it('sprint 2b does not create jornada event or calculation tables', function (): void {
    expect(Schema::hasTable('schedule_assignments'))->toBeTrue()
        ->and(Schema::hasTable('time_events'))->toBeFalse()
        ->and(Schema::hasTable('work_days'))->toBeFalse()
        ->and(Schema::hasTable('work_day_calculations'))->toBeFalse()
        ->and(Schema::hasTable('alerts'))->toBeFalse()
        ->and(Schema::hasTable('incidents'))->toBeFalse()
        ->and(Schema::hasTable('reports'))->toBeFalse();
});

function scheduleAssignmentUserWithCompany(Company $company, string $roleKey = 'owner'): User
{
    $role = Role::factory()->create(['key' => $roleKey]);
    $user = User::factory()->create();

    $user->companies()->attach($company, [
        'role_id' => $role->id,
        'status' => 'active',
        'is_default' => true,
    ]);

    return $user;
}

function scheduleAssignmentFixture(): array
{
    $company = Company::factory()->create();
    $user = scheduleAssignmentUserWithCompany($company);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $schedule = Schedule::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
    ]);

    return [$company, $user, $worker, $schedule, $relationship];
}
