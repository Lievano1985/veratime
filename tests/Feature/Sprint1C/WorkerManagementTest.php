<?php

use App\Domains\Workers\Actions\CreateWorkerAction;
use App\Domains\Workers\Actions\SaveWorkerWithEmploymentRelationshipAction;
use App\Domains\Workers\Actions\UpdateWorkerAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\ScheduleBatch;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerCredential;
use App\Support\RoleKey;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;

it('guest cannot access workers route', function (): void {
    $this->get(route('workers.index'))
        ->assertRedirect(route('login'));
});

it('authenticated user without active company cannot access workers route', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('workers.index'))
        ->assertForbidden();
});

it('user sees only workers from active company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = workerUserWithCompany($company);
    $visibleWorker = Worker::factory()->create([
        'company_id' => $company->id,
        'full_name' => 'Trabajador visible',
    ]);
    $foreignWorker = Worker::factory()->create([
        'company_id' => $otherCompany->id,
        'full_name' => 'Trabajador ajeno',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->assertSee($visibleWorker->full_name)
        ->assertDontSee($foreignWorker->full_name);
});

it('user cannot see workers from another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = workerUserWithCompany($company);
    $foreignWorker = Worker::factory()->create([
        'company_id' => $otherCompany->id,
        'full_name' => 'Trabajador ajeno',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->assertDontSee($foreignWorker->full_name);
});

it('user can create worker in active company', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->set('form.employee_code', 'EMP-001')
        ->set('form.full_name', 'Maria Lopez')
        ->set('form.center_id', (string) $center->id)
        ->set('form.started_at', '2026-07-01')
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('workers', [
        'company_id' => $company->id,
        'employee_code' => 'EMP-001',
        'full_name' => 'Maria Lopez',
        'email' => null,
        'status' => 'active',
        'source' => 'web',
    ]);

    $worker = Worker::query()->where('employee_code', 'EMP-001')->firstOrFail();

    $this->assertDatabaseHas('employment_relationships', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => null,
        'started_at' => '2026-07-01 00:00:00',
        'status' => 'active',
        'source' => 'web',
    ]);
});

it('user cannot create duplicate employee code within same company', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    Worker::factory()->create([
        'company_id' => $company->id,
        'employee_code' => 'EMP-DUP',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->set('form.employee_code', 'EMP-DUP')
        ->set('form.full_name', 'Duplicado')
        ->set('form.center_id', (string) $center->id)
        ->set('form.started_at', '2026-07-01')
        ->set('form.status', 'active')
        ->call('save')
        ->assertHasErrors(['form.employee_code']);
});

it('employee code can repeat in another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    Worker::factory()->create([
        'company_id' => $otherCompany->id,
        'employee_code' => 'SHARED',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->set('form.employee_code', 'SHARED')
        ->set('form.full_name', 'Trabajador propio')
        ->set('form.center_id', (string) $center->id)
        ->set('form.started_at', '2026-07-01')
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('workers', [
        'company_id' => $company->id,
        'employee_code' => 'SHARED',
    ]);
});

it('user can update own worker', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create([
        'company_id' => $company->id,
        'employee_code' => 'OLD',
        'full_name' => 'Anterior',
    ]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('form.employee_code', 'NEW')
        ->set('form.full_name', 'Actualizado')
        ->set('form.center_id', (string) $center->id)
        ->set('form.started_at', now()->subMonth()->toDateString())
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('workers', [
        'id' => $worker->id,
        'company_id' => $company->id,
        'employee_code' => 'NEW',
        'full_name' => 'Actualizado',
    ]);

    expect(EmploymentRelationship::query()->where('worker_id', $worker->id)->count())->toBe(1);
});

it('corrects center on the same employment relationship when there is no protected evidence', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $newCenter = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-01',
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('form.center_id', (string) $newCenter->id)
        ->set('form.started_at', '2026-06-15')
        ->set('form.relationship_change_reason', 'Centro capturado incorrectamente en alta.')
        ->call('save');

    $this->assertDatabaseHas('employment_relationships', [
        'id' => $relationship->id,
        'center_id' => $newCenter->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-06-15 00:00:00',
        'status' => 'active',
        'ended_at' => null,
    ]);

    $relationship->refresh();

    expect(EmploymentRelationship::query()->where('worker_id', $worker->id)->count())->toBe(1)
        ->and($relationship->metadata['administrative_corrections'][0]['reason'])->toBe('Centro capturado incorrectamente en alta.')
        ->and($relationship->metadata['administrative_corrections'][0]['previous']['center_id'])->toBe($center->id);
});

it('corrects position on the same employment relationship when there is no protected evidence', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-01',
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    app(SaveWorkerWithEmploymentRelationshipAction::class)->handle($company, $worker, $center, [
        'employee_code' => $worker->employee_code,
        'full_name' => $worker->full_name,
        'status' => $worker->status,
        'position_name' => 'Supervisor',
        'started_at' => '2026-07-01',
        'relationship_change_reason' => 'Puesto capturado incorrectamente.',
    ], $user);

    $this->assertDatabaseHas('employment_relationships', [
        'id' => $relationship->id,
        'center_id' => $center->id,
        'position_name' => 'Supervisor',
        'started_at' => '2026-07-01 00:00:00',
        'status' => 'active',
        'ended_at' => null,
    ]);

    expect(EmploymentRelationship::query()->where('worker_id', $worker->id)->count())->toBe(1);
});

it('corrects started at on the same employment relationship when there is no protected evidence', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-01',
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('form.center_id', (string) $center->id)
        ->set('form.started_at', '2026-06-20')
        ->set('form.relationship_change_reason', 'Fecha de ingreso capturada incorrectamente.')
        ->call('save');

    $this->assertDatabaseHas('employment_relationships', [
        'id' => $relationship->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-06-20 00:00:00',
        'status' => 'active',
        'ended_at' => null,
    ]);

    expect(EmploymentRelationship::query()->where('worker_id', $worker->id)->count())->toBe(1);
});

it('editing worker basics without relationship changes does not create a new relationship', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create([
        'company_id' => $company->id,
        'employee_code' => 'BASIC-1',
        'full_name' => 'Nombre original',
    ]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-01',
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('form.employee_code', 'BASIC-2')
        ->set('form.full_name', 'Nombre actualizado')
        ->set('form.center_id', (string) $center->id)
        ->set('form.started_at', '2026-07-01')
        ->call('save');

    $this->assertDatabaseHas('employment_relationships', [
        'id' => $relationship->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-01 00:00:00',
        'status' => 'active',
        'ended_at' => null,
    ]);

    expect(EmploymentRelationship::query()->where('worker_id', $worker->id)->count())->toBe(1);
});

it('worker edit form resets inputs when closing and opening create panel', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create([
        'company_id' => $company->id,
        'employee_code' => 'RESET-1',
        'full_name' => 'Trabajador Reset',
    ]);
    EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-01',
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('form.relationship_change_reason', 'Motivo temporal')
        ->set('credentialForm.temporal_pin', '1234')
        ->call('closeFormPanel')
        ->call('openCreatePanel')
        ->assertSet('editingWorkerId', null)
        ->assertSet('form.employee_code', '')
        ->assertSet('form.full_name', '')
        ->assertSet('form.relationship_change_reason', '')
        ->assertSet('credentialForm.temporal_pin', '');
});

it('requires reason when relationship data changes', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $newCenter = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-10',
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('form.center_id', (string) $newCenter->id)
        ->set('form.started_at', '2026-06-10')
        ->call('save')
        ->assertHasErrors(['form.relationship_change_reason']);

    expect(EmploymentRelationship::query()->where('worker_id', $worker->id)->count())->toBe(1);
});

it('blocks administrative correction when relationship already has protected published schedule evidence', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $newCenter = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-01',
        'status' => 'active',
    ]);
    $batch = ScheduleBatch::factory()->create([
        'company_id' => $company->id,
        'center_id' => $center->id,
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-15',
        'status' => 'published',
        'version' => 1,
        'published_at' => now(),
    ]);
    DailyScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_batch_id' => $batch->id,
        'employment_relationship_id' => $relationship->id,
        'work_date' => '2026-07-10',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('form.center_id', (string) $newCenter->id)
        ->set('form.started_at', '2026-06-15')
        ->set('form.relationship_change_reason', 'Centro y fecha capturados mal.')
        ->call('save')
        ->assertHasErrors(['form.relationship_change_reason']);

    $this->assertDatabaseHas('employment_relationships', [
        'id' => $relationship->id,
        'center_id' => $center->id,
        'started_at' => '2026-07-01 00:00:00',
        'status' => 'active',
        'ended_at' => null,
    ]);

    expect(EmploymentRelationship::query()->where('worker_id', $worker->id)->count())->toBe(1);
});

it('allows a new future relationship only after protected evidence dates', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $newCenter = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-01',
        'status' => 'active',
    ]);
    TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'employment_relationship_id' => $relationship->id,
        'center_id' => $center->id,
        'event_type' => 'clock_in',
        'occurred_local_date' => '2026-07-10',
        'status' => 'valid',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('form.center_id', (string) $newCenter->id)
        ->set('form.started_at', '2026-07-20')
        ->set('form.relationship_change_reason', 'Cambio operativo futuro.')
        ->call('save');

    $this->assertDatabaseHas('employment_relationships', [
        'id' => $relationship->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-01 00:00:00',
        'status' => 'ended',
        'ended_at' => '2026-07-19 00:00:00',
    ]);

    $this->assertDatabaseHas('employment_relationships', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $newCenter->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-20 00:00:00',
        'status' => 'active',
    ]);

    expect(EmploymentRelationship::query()->where('worker_id', $worker->id)->count())->toBe(2);
});

it('user cannot update worker from another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = workerUserWithCompany($company);
    $foreignWorker = Worker::factory()->create([
        'company_id' => $otherCompany->id,
        'employee_code' => 'FOREIGN',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    $this->assertFalse($user->can('update', $foreignWorker));
    $this->expectException(ModelNotFoundException::class);

    Volt::test('workers.index')
        ->call('loadEditForm', $foreignWorker->id);
});

it('terminate is non destructive and keeps worker record', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
    ]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'status' => 'active',
        'ended_at' => null,
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('terminate', $worker->id);

    $this->assertDatabaseHas('workers', [
        'id' => $worker->id,
        'status' => 'terminated',
    ]);

    $this->assertDatabaseCount('workers', 1);
});

it('terminate closes active employment relationship with ended at', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'status' => 'active',
        'ended_at' => null,
    ]);
    $unit = OrganizationalUnit::factory()->create([
        'company_id' => $company->id,
        'center_id' => $center->id,
    ]);
    $assignment = new EmploymentUnitAssignment([
        'employment_relationship_id' => $relationship->id,
        'organizational_unit_id' => $unit->id,
        'assignment_type' => 'primary',
        'effective_from' => now()->subMonth()->toDateString(),
        'effective_to' => null,
        'status' => 'active',
    ]);
    $assignment->company()->associate($company);
    $assignment->save();

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('terminate', $worker->id);

    $relationship->refresh();
    $assignment->refresh();

    $this->assertSame('ended', $relationship->status);
    $this->assertSame(now()->toDateString(), $relationship->ended_at->toDateString());
    $this->assertSame('inactive', $assignment->status);
    $this->assertSame(now()->toDateString(), $assignment->effective_to->toDateString());
});

it('employment relationship is created with center from same company', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->set('form.employee_code', 'REL-001')
        ->set('form.full_name', 'Relacion Valida')
        ->set('form.center_id', (string) $center->id)
        ->set('form.started_at', '2026-07-01')
        ->set('form.status', 'active')
        ->call('save');

    $worker = Worker::query()->where('employee_code', 'REL-001')->firstOrFail();

    $this->assertDatabaseHas('employment_relationships', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'status' => 'active',
    ]);
});

it('blocks employment relationship with center from another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $foreignCenter = Center::factory()->create(['company_id' => $otherCompany->id]);
    $user = workerUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->set('form.employee_code', 'REL-FOREIGN')
        ->set('form.full_name', 'Centro Ajeno')
        ->set('form.center_id', (string) $foreignCenter->id)
        ->set('form.started_at', '2026-07-01')
        ->set('form.status', 'active')
        ->call('save')
        ->assertHasErrors(['form.center_id']);

    $this->assertDatabaseMissing('workers', [
        'company_id' => $company->id,
        'employee_code' => 'REL-FOREIGN',
    ]);
});

it('worker form validates required fields and status', function (): void {
    $company = Company::factory()->create();
    $user = workerUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->set('form.employee_code', '')
        ->set('form.full_name', '')
        ->set('form.center_id', '')
        ->set('form.started_at', '')
        ->set('form.status', 'archived')
        ->call('save')
        ->assertHasErrors([
            'form.employee_code' => 'required',
            'form.full_name' => 'required',
            'form.center_id' => 'required',
            'form.started_at' => 'required',
            'form.status',
        ]);
});

it('inactive company cannot operate workers', function (): void {
    $company = Company::factory()->create(['status' => 'inactive']);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    $this->assertFalse($user->can('update', $worker));

    Volt::test('workers.index')
        ->assertForbidden();
});

it('unauthorized role cannot create edit or terminate workers', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company, 'supervisor');
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    $this->assertFalse($user->can('create', [Worker::class, $company]));
    $this->assertFalse($user->can('update', $worker));
    $this->assertFalse($user->can('terminate', $worker));

    Volt::test('workers.index')
        ->assertForbidden();

    $this->assertDatabaseHas('workers', [
        'id' => $worker->id,
        'status' => 'active',
    ]);
});

it('manipulated company id does not create worker in another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->set('form.company_id', $otherCompany->id)
        ->set('form.employee_code', 'SAFE-W')
        ->set('form.full_name', 'Trabajador Seguro')
        ->set('form.center_id', (string) $center->id)
        ->set('form.started_at', '2026-07-01')
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('workers', [
        'company_id' => $company->id,
        'employee_code' => 'SAFE-W',
    ]);

    $this->assertDatabaseMissing('workers', [
        'company_id' => $otherCompany->id,
        'employee_code' => 'SAFE-W',
    ]);
});

it('manipulated company id does not move existing worker to another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create([
        'company_id' => $company->id,
        'employee_code' => 'OWN-W',
    ]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('form.company_id', $otherCompany->id)
        ->set('form.employee_code', 'OWN-W2')
        ->set('form.full_name', 'Trabajador propio')
        ->set('form.center_id', (string) $center->id)
        ->set('form.started_at', $relationship->started_at->toDateString())
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('workers', [
        'id' => $worker->id,
        'company_id' => $company->id,
        'employee_code' => 'OWN-W2',
    ]);

    $this->assertDatabaseMissing('workers', [
        'id' => $worker->id,
        'company_id' => $otherCompany->id,
    ]);
});

it('worker creation flow does not create labor conditions or worker credentials by default', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->set('form.employee_code', 'NO-1D')
        ->set('form.full_name', 'Sin Sprint 1D automatico')
        ->set('form.center_id', (string) $center->id)
        ->set('form.started_at', '2026-07-01')
        ->set('form.status', 'active')
        ->call('save');

    expect(Schema::hasTable('labor_conditions'))->toBeFalse()
        ->and(Schema::hasTable('worker_credentials'))->toBeTrue()
        ->and(WorkerCredential::query()->count())->toBe(0);
});

function workerUserWithCompany(Company $company, string $roleKey = RoleKey::ADMIN_EMPRESA): User
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

it('worker actions normalize an empty birth date and preserve valid dates', function (): void {
    $company = Company::factory()->create();
    $data = ['employee_code' => 'EMPTY-DATE', 'full_name' => 'Fecha opcional', 'birth_date' => '', 'status' => 'active'];
    $worker = app(CreateWorkerAction::class)->handle($company, $data);
    expect($worker->fresh()->getRawOriginal('birth_date'))->toBeNull();
    $worker->update(['birth_date' => '1990-01-01']);
    $updated = app(UpdateWorkerAction::class)->handle($worker, $data);
    expect($updated->getRawOriginal('birth_date'))->toBeNull();
    $data['birth_date'] = '1991-02-03';
    expect(app(UpdateWorkerAction::class)->handle($worker, $data)->birth_date->format('Y-m-d'))->toBe('1991-02-03');
});

it('simplifies the worker form while preserving existing contact data and operational fields', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = workerUserWithCompany($company);
    $worker = Worker::factory()->create([
        'company_id' => $company->id,
        'email' => 'kept@example.test',
        'phone' => '5551234567',
        'birth_date' => '1990-01-01',
        'rfc' => 'RFC-PRESERVADO',
        'curp' => 'CURP-PRESERVADA',
    ]);
    EmploymentRelationship::factory()->create([
        'company_id' => $company->id, 'worker_id' => $worker->id,
        'center_id' => $center->id, 'started_at' => '2026-07-01',
    ]);
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    Volt::test('workers.index')->call('loadEditForm', $worker->id)
        ->assertDontSeeHtml('wire:model="form.email"')
        ->assertDontSeeHtml('wire:model="form.phone"')
        ->assertDontSeeHtml('wire:model="form.birth_date"')
        ->assertDontSeeHtml('wire:model="form.curp"')
        ->assertDontSeeHtml('wire:model="form.position_name"')
        ->assertSee('Identificación')->assertSee('Datos laborales')->assertSee('RFC')
        ->set('form.full_name', 'Nombre actualizado')->call('save')->assertHasNoErrors();
    expect($worker->fresh()->full_name)->toBe('Nombre actualizado')
        ->and($worker->fresh()->email)->toBe('kept@example.test')
        ->and($worker->fresh()->phone)->toBe('5551234567')
        ->and($worker->fresh()->rfc)->toBe('RFC-PRESERVADO')
        ->and($worker->fresh()->curp)->toBe('CURP-PRESERVADA')
        ->and($worker->fresh()->birth_date->toDateString())->toBe('1990-01-01');
});

it('requests a reason only when center or employment start changes', function (): void {
    $company = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $otherCenter = Center::factory()->create(['company_id' => $company->id]);
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    EmploymentRelationship::factory()->create([
        'company_id' => $company->id, 'worker_id' => $worker->id, 'center_id' => $center->id,
        'started_at' => '2026-07-01', 'position_name' => 'Puesto histórico',
    ]);
    $this->actingAs(workerUserWithCompany($company))->withSession(['current_company_id' => $company->id]);
    Volt::test('workers.index')->call('loadEditForm', $worker->id)
        ->assertDontSee('Motivo del cambio laboral')
        ->set('form.full_name', 'Nombre corregido')->assertDontSee('Motivo del cambio laboral')
        ->set('form.center_id', (string) $otherCenter->id)->assertSee('Motivo del cambio laboral')
        ->set('form.center_id', (string) $center->id)->assertDontSee('Motivo del cambio laboral')
        ->set('form.started_at', '2026-07-02')->assertSee('Motivo del cambio laboral')
        ->set('form.started_at', '2026-07-01')->assertDontSee('Motivo del cambio laboral')
        ->call('save')->assertHasNoErrors();
    expect($worker->fresh()->activeEmploymentRelationship->position_name)->toBe('Puesto histórico');
});
