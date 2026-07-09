<?php

use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
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
        ->set('form.email', 'maria@example.test')
        ->set('form.center_id', (string) $center->id)
        ->set('form.position_name', 'Operadora')
        ->set('form.started_at', '2026-07-01')
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('workers', [
        'company_id' => $company->id,
        'employee_code' => 'EMP-001',
        'full_name' => 'Maria Lopez',
        'email' => 'maria@example.test',
        'status' => 'active',
        'source' => 'web',
    ]);

    $worker = Worker::query()->where('employee_code', 'EMP-001')->firstOrFail();

    $this->assertDatabaseHas('employment_relationships', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Operadora',
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
    EmploymentRelationship::factory()->create([
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
        ->set('form.position_name', 'Auxiliar')
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

it('changing center creates a new employment relationship and closes the previous one', function (): void {
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
        ->set('form.position_name', 'Auxiliar')
        ->set('form.started_at', '2026-07-10')
        ->call('save');

    $this->assertDatabaseHas('employment_relationships', [
        'id' => $relationship->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-01 00:00:00',
        'status' => 'ended',
        'ended_at' => '2026-07-09 00:00:00',
    ]);

    $this->assertDatabaseHas('employment_relationships', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $newCenter->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-10 00:00:00',
        'status' => 'active',
    ]);

    expect(EmploymentRelationship::query()->where('worker_id', $worker->id)->count())->toBe(2);
});

it('changing position creates a new employment relationship and preserves the previous one', function (): void {
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
        ->set('form.position_name', 'Supervisor')
        ->set('form.started_at', '2026-07-15')
        ->call('save');

    $this->assertDatabaseHas('employment_relationships', [
        'id' => $relationship->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-01 00:00:00',
        'status' => 'ended',
        'ended_at' => '2026-07-14 00:00:00',
    ]);

    $this->assertDatabaseHas('employment_relationships', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Supervisor',
        'started_at' => '2026-07-15 00:00:00',
        'status' => 'active',
    ]);

    expect(EmploymentRelationship::query()->where('worker_id', $worker->id)->count())->toBe(2);
});

it('changing started at creates a new employment relationship and preserves the previous one', function (): void {
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
        ->set('form.position_name', 'Auxiliar')
        ->set('form.started_at', '2026-07-20')
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
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-20 00:00:00',
        'status' => 'active',
    ]);

    expect(EmploymentRelationship::query()->where('worker_id', $worker->id)->count())->toBe(2);
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
        ->set('form.position_name', 'Auxiliar')
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

it('does not allow a replacement relationship to overlap or start before the active one', function (): void {
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
        ->set('form.started_at', '2026-07-10')
        ->call('save')
        ->assertHasErrors(['form.started_at']);

    expect(EmploymentRelationship::query()->where('worker_id', $worker->id)->count())->toBe(1);
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
    EmploymentRelationship::factory()->create([
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

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('terminate', $worker->id);

    $this->assertDatabaseHas('employment_relationships', [
        'id' => $relationship->id,
        'status' => 'ended',
        'ended_at' => now()->toDateString(),
    ]);
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
        ->set('form.position_name', 'General')
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

it('worker form validates required fields email and status', function (): void {
    $company = Company::factory()->create();
    $user = workerUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->set('form.employee_code', '')
        ->set('form.full_name', '')
        ->set('form.email', 'invalid-email')
        ->set('form.center_id', '')
        ->set('form.started_at', '')
        ->set('form.status', 'archived')
        ->call('save')
        ->assertHasErrors([
            'form.employee_code' => 'required',
            'form.full_name' => 'required',
            'form.email' => 'email',
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
    EmploymentRelationship::factory()->create([
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
    EmploymentRelationship::factory()->create([
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
        ->set('form.started_at', '2026-07-01')
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

it('does not create labor conditions or worker credentials in sprint 1c', function (): void {
    expect(Schema::hasTable('labor_conditions'))->toBeFalse()
        ->and(Schema::hasTable('worker_credentials'))->toBeFalse();
});

function workerUserWithCompany(Company $company, string $roleKey = 'owner'): User
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
