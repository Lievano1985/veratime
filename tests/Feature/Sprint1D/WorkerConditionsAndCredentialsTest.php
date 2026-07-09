<?php

use App\Domains\Workers\Actions\BlockWorkerCredentialAction;
use App\Domains\Workers\Actions\CreateOrReplaceLaborConditionAction;
use App\Domains\Workers\Actions\CreateOrUpdateWorkerCredentialAction;
use App\Domains\Workers\Actions\ResetWorkerCredentialPinAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\LaborCondition;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerCredential;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;

it('creates labor conditions table with mysql compatible json metadata', function (): void {
    expect(Schema::hasTable('labor_conditions'))->toBeTrue()
        ->and(Schema::hasColumn('labor_conditions', 'metadata'))->toBeTrue();
});

it('creates worker credentials table without plaintext pin column', function (): void {
    expect(Schema::hasTable('worker_credentials'))->toBeTrue()
        ->and(Schema::hasColumn('worker_credentials', 'pin_hash'))->toBeTrue()
        ->and(Schema::hasColumn('worker_credentials', 'temporal_pin'))->toBeFalse();
});

it('user can create an initial labor condition for own active relationship', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext();

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('conditionForm.work_modality', 'hybrid')
        ->set('conditionForm.weekly_hours', '40')
        ->set('conditionForm.rest_day_of_week', '0')
        ->set('conditionForm.effective_from', '2026-08-01')
        ->set('conditionForm.status', 'active')
        ->call('saveLaborCondition');

    $condition = LaborCondition::query()->first();

    expect($condition)->not->toBeNull()
        ->and($condition->company_id)->toBe($company->id)
        ->and($condition->employment_relationship_id)->toBe($worker->activeEmploymentRelationship->id)
        ->and($condition->work_modality)->toBe('hybrid')
        ->and($condition->effective_from->toDateString())->toBe('2026-08-01')
        ->and($condition->status)->toBe('active');
});

it('replacing active labor condition closes previous condition and preserves history', function (): void {
    [$company, $user, $worker, $relationship] = sprint1DWorkerContext();
    $original = LaborCondition::factory()->create([
        'company_id' => $company->id,
        'employment_relationship_id' => $relationship->id,
        'work_modality' => 'onsite',
        'weekly_hours' => 48,
        'effective_from' => '2026-08-01',
        'effective_to' => null,
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('conditionForm.work_modality', 'remote')
        ->set('conditionForm.weekly_hours', '40')
        ->set('conditionForm.effective_from', '2026-08-15')
        ->set('conditionForm.status', 'active')
        ->call('saveLaborCondition');

    $original->refresh();

    expect($original->work_modality)->toBe('onsite')
        ->and($original->weekly_hours)->toBe('48.00')
        ->and($original->effective_from->toDateString())->toBe('2026-08-01')
        ->and($original->status)->toBe('replaced')
        ->and($original->effective_to->toDateString())->toBe('2026-08-14')
        ->and(LaborCondition::query()->where('employment_relationship_id', $relationship->id)->count())->toBe(2);
});

it('blocks active labor condition overlap with same or previous effective date', function (): void {
    [$company, $user, $worker, $relationship] = sprint1DWorkerContext();
    LaborCondition::factory()->create([
        'company_id' => $company->id,
        'employment_relationship_id' => $relationship->id,
        'effective_from' => '2026-08-10',
        'effective_to' => null,
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('conditionForm.work_modality', 'field')
        ->set('conditionForm.effective_from', '2026-08-10')
        ->call('saveLaborCondition')
        ->assertHasErrors(['conditionForm.effective_from']);

    expect(LaborCondition::query()->where('employment_relationship_id', $relationship->id)->count())->toBe(1);
});

it('validates labor condition required and valid fields', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext();

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('conditionForm.work_modality', 'invalid')
        ->set('conditionForm.weekly_hours', '200')
        ->set('conditionForm.rest_day_of_week', '9')
        ->set('conditionForm.effective_from', '')
        ->set('conditionForm.status', 'archived')
        ->call('saveLaborCondition')
        ->assertHasErrors([
            'conditionForm.work_modality',
            'conditionForm.weekly_hours',
            'conditionForm.rest_day_of_week',
            'conditionForm.effective_from' => 'required',
            'conditionForm.status',
        ]);
});

it('does not allow labor condition for relationship from another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $worker = Worker::factory()->create(['company_id' => $company->id]);
    $foreignWorker = Worker::factory()->create(['company_id' => $otherCompany->id]);
    $foreignRelationship = EmploymentRelationship::factory()->create([
        'company_id' => $otherCompany->id,
        'worker_id' => $foreignWorker->id,
    ]);

    $this->expectException(InvalidArgumentException::class);

    app(CreateOrReplaceLaborConditionAction::class)->handle($company, $worker, $foreignRelationship, [
        'work_modality' => 'onsite',
        'effective_from' => '2026-08-01',
        'status' => 'active',
    ]);
});

it('does not allow unauthorized role to create labor conditions', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext('supervisor');

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->assertForbidden();

    expect(LaborCondition::query()->count())->toBe(0)
        ->and($user->can('create', [LaborCondition::class, $company, $worker->activeEmploymentRelationship]))->toBeFalse();
});

it('blocks labor condition operations when company is inactive', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext(companyState: ['status' => 'inactive']);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    expect($user->can('create', [LaborCondition::class, $company, $worker->activeEmploymentRelationship]))->toBeFalse();

    Volt::test('workers.index')
        ->assertForbidden();
});

it('creates worker credential with hashed pin and no plaintext exposure', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext();

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('credentialForm.access_code', 'ACCESO-1')
        ->set('credentialForm.temporal_pin', '1234')
        ->set('credentialForm.status', 'active')
        ->call('saveCredential')
        ->assertSet('credentialForm.temporal_pin', '');

    $credential = $worker->credential()->firstOrFail();

    expect($credential->company_id)->toBe($company->id)
        ->and($credential->pin_hash)->not->toBe('1234')
        ->and(Hash::check('1234', $credential->pin_hash))->toBeTrue()
        ->and($credential->toArray())->not->toHaveKey('pin_hash');
});

it('requires temporal pin when creating credential', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext();

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('credentialForm.access_code', 'ACCESO-2')
        ->set('credentialForm.temporal_pin', '')
        ->call('saveCredential')
        ->assertHasErrors(['credentialForm.temporal_pin' => 'required'])
        ->assertSet('credentialForm.temporal_pin', '');
});

it('validates credential status and pin length', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext();

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('credentialForm.access_code', '')
        ->set('credentialForm.temporal_pin', '123')
        ->set('credentialForm.status', 'invalid')
        ->call('saveCredential')
        ->assertHasErrors([
            'credentialForm.access_code' => 'required',
            'credentialForm.temporal_pin' => 'min',
            'credentialForm.status',
        ])
        ->assertSet('credentialForm.temporal_pin', '');
});

it('keeps access code unique per company', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext();
    $otherWorker = Worker::factory()->create(['company_id' => $company->id]);
    WorkerCredential::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $otherWorker->id,
        'access_code' => 'UNICO',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('credentialForm.access_code', 'UNICO')
        ->set('credentialForm.temporal_pin', '1234')
        ->call('saveCredential')
        ->assertHasErrors(['credentialForm.access_code'])
        ->assertSet('credentialForm.temporal_pin', '');
});

it('allows same access code in different companies', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext();
    $otherCompany = Company::factory()->create();
    $otherWorker = Worker::factory()->create(['company_id' => $otherCompany->id]);
    WorkerCredential::factory()->create([
        'company_id' => $otherCompany->id,
        'worker_id' => $otherWorker->id,
        'access_code' => 'COMPARTIDO',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('credentialForm.access_code', 'COMPARTIDO')
        ->set('credentialForm.temporal_pin', '1234')
        ->call('saveCredential');

    $this->assertDatabaseHas('worker_credentials', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'access_code' => 'COMPARTIDO',
    ]);
});

it('does not allow credential creation for worker from another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $foreignWorker = Worker::factory()->create(['company_id' => $otherCompany->id]);

    $this->expectException(InvalidArgumentException::class);

    app(CreateOrUpdateWorkerCredentialAction::class)->handle($company, $foreignWorker, [
        'access_code' => 'FOREIGN',
        'temporal_pin' => '1234',
        'status' => 'active',
    ]);
});

it('manipulated company id does not create credential in another company', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext();
    $otherCompany = Company::factory()->create();

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('credentialForm.company_id', $otherCompany->id)
        ->set('credentialForm.access_code', 'SAFE-CRED')
        ->set('credentialForm.temporal_pin', '1234')
        ->call('saveCredential');

    $this->assertDatabaseHas('worker_credentials', [
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'access_code' => 'SAFE-CRED',
    ]);

    $this->assertDatabaseMissing('worker_credentials', [
        'company_id' => $otherCompany->id,
        'access_code' => 'SAFE-CRED',
    ]);
});

it('resets credential pin and blocks credential through actions', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext();
    $credential = WorkerCredential::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'status' => 'active',
        'last_changed_at' => now()->subDay(),
    ]);
    $previousChangedAt = $credential->last_changed_at;

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('credentialForm.temporal_pin', '5678')
        ->call('resetCredentialPin')
        ->assertSet('credentialForm.temporal_pin', '')
        ->call('blockCredential');

    $credential->refresh();

    expect(Hash::check('5678', $credential->pin_hash))->toBeTrue()
        ->and($credential->status)->toBe('blocked')
        ->and($credential->failed_attempts)->toBe(0)
        ->and($credential->last_changed_at->greaterThan($previousChangedAt))->toBeTrue();
});

it('clears temporal pin when reset validation fails because pin is too short', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext();
    WorkerCredential::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->set('credentialForm.temporal_pin', '123')
        ->call('resetCredentialPin')
        ->assertHasErrors(['credentialForm.temporal_pin' => 'min'])
        ->assertSet('credentialForm.temporal_pin', '');
});

it('does not allow unauthorized role to create update reset or block credentials', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext('supervisor');
    $credential = WorkerCredential::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
    ]);

    expect($user->can('create', [WorkerCredential::class, $company, $worker]))->toBeFalse()
        ->and($user->can('update', $credential))->toBeFalse()
        ->and($user->can('reset', $credential))->toBeFalse()
        ->and($user->can('block', $credential))->toBeFalse();
});

it('does not expose pin hash in worker screen', function (): void {
    [$company, $user, $worker] = sprint1DWorkerContext();
    $credential = WorkerCredential::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'pin_hash' => Hash::make('9999'),
        'access_code' => 'VISIBLE',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('workers.index')
        ->call('loadEditForm', $worker->id)
        ->assertDontSee($credential->pin_hash);
});

it('does not create schedule assignments, kiosk events, labor condition schedules, or worker credential auth flow', function (): void {
    expect(Schema::hasTable('schedule_assignments'))->toBeFalse()
        ->and(Schema::hasTable('time_entries'))->toBeFalse()
        ->and(Schema::hasTable('labor_conditions'))->toBeTrue()
        ->and(Schema::hasTable('worker_credentials'))->toBeTrue();
});

it('worker screen does not expose workers from another company for conditions or credentials', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = sprint1DUserWithCompany($company);
    $foreignWorker = Worker::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    $this->expectException(ModelNotFoundException::class);

    Volt::test('workers.index')
        ->call('loadEditForm', $foreignWorker->id);
});

function sprint1DWorkerContext(string $roleKey = 'owner', array $companyState = []): array
{
    $company = Company::factory()->create($companyState);
    $center = Center::factory()->create(['company_id' => $company->id]);
    $user = sprint1DUserWithCompany($company, $roleKey);
    $worker = Worker::factory()->create([
        'company_id' => $company->id,
        'employee_code' => 'EMP-'.fake()->unique()->numberBetween(1000, 9999),
    ]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'center_id' => $center->id,
        'position_name' => 'Auxiliar',
        'started_at' => '2026-07-01',
        'status' => 'active',
    ]);

    return [$company, $user, $worker->refresh(), $relationship];
}

function sprint1DUserWithCompany(Company $company, string $roleKey = 'owner'): User
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
