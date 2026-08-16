<?php

use App\Domains\Companies\Actions\DeleteCenterIfUnusedAction;
use App\Domains\MandatoryRestDays\Actions\DeleteMandatoryRestDayIfUnusedAction;
use App\Domains\Organization\Actions\DeleteOrganizationalUnitIfUnusedAction;
use App\Domains\Schedules\Actions\DeleteScheduleAssignmentIfUnusedAction;
use App\Domains\Schedules\Actions\DeleteScheduleIfUnusedAction;
use App\Domains\Scheduling\Actions\DeleteScheduleProfileAssignmentIfUnusedAction;
use App\Domains\Scheduling\Actions\DeleteScheduleProfileIfUnusedAction;
use App\Domains\Scheduling\Actions\DeleteShiftTemplateIfUnusedAction;
use App\Domains\Workers\Actions\DeleteWorkerIfUnusedAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\MandatoryRestDay;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleBatch;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileAssignment;
use App\Models\ScheduleProfileWeeklyRule;
use App\Models\ShiftTemplate;
use App\Models\ShiftTemplateSegment;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerCredential;
use App\Support\RoleKey;
use Livewire\Volt\Volt;

function catalogDeletionCompany(): Company
{
    return Company::factory()->create(['status' => 'active']);
}

function catalogDeletionRelationship(Company $company): EmploymentRelationship
{
    return EmploymentRelationship::factory()->create(['company_id' => $company->id]);
}

function catalogDeletionBatchFor(Company $company, Center $center): ScheduleBatch
{
    return ScheduleBatch::factory()->create([
        'company_id' => $company->id,
        'center_id' => $center->id,
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-07',
    ]);
}

function catalogDeletionUserFor(Company $company, string $roleKey = RoleKey::ADMIN_EMPRESA): User
{
    $role = Role::factory()->create(['key' => $roleKey]);
    $user = User::factory()->create(['status' => 'active']);

    $user->companies()->attach($company, [
        'role_id' => $role->id,
        'status' => 'active',
        'is_default' => true,
    ]);

    return $user;
}

it('deletes centers only when they are not used by schedule history', function (): void {
    $company = catalogDeletionCompany();
    $unusedCenter = Center::factory()->create(['company_id' => $company->id]);
    $usedCenter = Center::factory()->create(['company_id' => $company->id]);

    catalogDeletionBatchFor($company, $usedCenter);

    app(DeleteCenterIfUnusedAction::class)->handle($unusedCenter);

    $this->assertModelMissing($unusedCenter);
    expect(fn () => app(DeleteCenterIfUnusedAction::class)->handle($usedCenter))
        ->toThrow(\InvalidArgumentException::class);
    $this->assertModelExists($usedCenter);
});

it('deletes organizational units only when no generated daily schedule uses them', function (): void {
    $company = catalogDeletionCompany();
    $unusedUnit = OrganizationalUnit::factory()->create(['company_id' => $company->id]);
    $usedUnit = OrganizationalUnit::factory()->create(['company_id' => $company->id]);
    $relationship = catalogDeletionRelationship($company);
    $batch = catalogDeletionBatchFor($company, Center::factory()->create(['company_id' => $company->id]));

    DailyScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_batch_id' => $batch->id,
        'employment_relationship_id' => $relationship->id,
        'organizational_unit_id' => $usedUnit->id,
        'source_type' => 'manual',
    ]);

    app(DeleteOrganizationalUnitIfUnusedAction::class)->handle($company, $unusedUnit);

    $this->assertModelMissing($unusedUnit);
    expect(fn () => app(DeleteOrganizationalUnitIfUnusedAction::class)->handle($company, $usedUnit))
        ->toThrow(\InvalidArgumentException::class);
    $this->assertModelExists($usedUnit);
});

it('deletes workers only when they have no assignments or attendance history', function (): void {
    $company = catalogDeletionCompany();
    $unusedWorker = Worker::factory()->create(['company_id' => $company->id]);
    $usedWorker = Worker::factory()->create(['company_id' => $company->id]);
    $relationship = EmploymentRelationship::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $unusedWorker->id,
    ]);

    WorkerCredential::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $unusedWorker->id,
    ]);
    ScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $usedWorker->id,
    ]);

    app(DeleteWorkerIfUnusedAction::class)->handle($unusedWorker);

    $this->assertModelMissing($unusedWorker);
    $this->assertDatabaseMissing('employment_relationships', ['id' => $relationship->id]);
    $this->assertDatabaseMissing('worker_credentials', ['worker_id' => $unusedWorker->id]);
    expect(fn () => app(DeleteWorkerIfUnusedAction::class)->handle($usedWorker))
        ->toThrow(\InvalidArgumentException::class);
    $this->assertModelExists($usedWorker);
});

it('deletes shift templates only when no profile rule or daily schedule references them', function (): void {
    $company = catalogDeletionCompany();
    $unusedShift = ShiftTemplate::factory()->create(['company_id' => $company->id]);
    $usedShift = ShiftTemplate::factory()->create(['company_id' => $company->id]);
    $profile = ScheduleProfile::factory()->create(['company_id' => $company->id]);

    ShiftTemplateSegment::factory()->create([
        'company_id' => $company->id,
        'shift_template_id' => $unusedShift->id,
    ]);
    ScheduleProfileWeeklyRule::factory()->create([
        'company_id' => $company->id,
        'schedule_profile_id' => $profile->id,
        'shift_template_id' => $usedShift->id,
    ]);

    app(DeleteShiftTemplateIfUnusedAction::class)->handle($company, $unusedShift);

    $this->assertModelMissing($unusedShift);
    $this->assertDatabaseMissing('shift_template_segments', ['shift_template_id' => $unusedShift->id]);
    expect(fn () => app(DeleteShiftTemplateIfUnusedAction::class)->handle($company, $usedShift))
        ->toThrow(\InvalidArgumentException::class);
    $this->assertModelExists($usedShift);
});

it('deletes schedule profiles with unused assignments and blocks generated schedules', function (): void {
    $company = catalogDeletionCompany();
    $unusedProfile = ScheduleProfile::factory()->create(['company_id' => $company->id]);
    $profileWithAssignment = ScheduleProfile::factory()->create(['company_id' => $company->id]);
    $generatedProfile = ScheduleProfile::factory()->create(['company_id' => $company->id]);
    $center = Center::factory()->create(['company_id' => $company->id]);
    $batch = catalogDeletionBatchFor($company, $center);
    $relationship = catalogDeletionRelationship($company);

    ScheduleProfileAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_profile_id' => $profileWithAssignment->id,
    ]);
    $generatedAssignment = ScheduleProfileAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_profile_id' => $generatedProfile->id,
    ]);
    DailyScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_batch_id' => $batch->id,
        'employment_relationship_id' => $relationship->id,
        'source_type' => 'profile',
        'source_reference' => [
            'schedule_profile_id' => $generatedProfile->id,
            'schedule_profile_assignment_id' => $generatedAssignment->id,
        ],
    ]);

    app(DeleteScheduleProfileIfUnusedAction::class)->handle($company, $unusedProfile);
    app(DeleteScheduleProfileIfUnusedAction::class)->handle($company, $profileWithAssignment);

    $this->assertModelMissing($unusedProfile);
    $this->assertModelMissing($profileWithAssignment);
    $this->assertDatabaseMissing('schedule_profile_assignments', ['schedule_profile_id' => $profileWithAssignment->id]);
    expect(fn () => app(DeleteScheduleProfileIfUnusedAction::class)->handle($company, $generatedProfile))
        ->toThrow(\InvalidArgumentException::class);
    $this->assertModelExists($generatedProfile);
});

it('deletes schedule profile assignments only before they generate daily schedules', function (): void {
    $company = catalogDeletionCompany();
    $profile = ScheduleProfile::factory()->create(['company_id' => $company->id]);
    $unusedAssignment = ScheduleProfileAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_profile_id' => $profile->id,
    ]);
    $usedAssignment = ScheduleProfileAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_profile_id' => $profile->id,
    ]);
    $center = Center::factory()->create(['company_id' => $company->id]);
    $batch = catalogDeletionBatchFor($company, $center);
    $relationship = catalogDeletionRelationship($company);

    DailyScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_batch_id' => $batch->id,
        'employment_relationship_id' => $relationship->id,
        'source_type' => 'profile',
        'source_reference' => [
            'schedule_profile_id' => $profile->id,
            'schedule_profile_assignment_id' => $usedAssignment->id,
        ],
    ]);

    app(DeleteScheduleProfileAssignmentIfUnusedAction::class)->handle($company, $unusedAssignment);

    $this->assertModelMissing($unusedAssignment);
    expect(fn () => app(DeleteScheduleProfileAssignmentIfUnusedAction::class)->handle($company, $usedAssignment))
        ->toThrow(\InvalidArgumentException::class);
    $this->assertModelExists($usedAssignment);
});

it('deletes legacy schedules and assignments only before they have active usage', function (): void {
    $company = catalogDeletionCompany();
    $unusedSchedule = Schedule::factory()->create(['company_id' => $company->id]);
    $deletableAssignmentSchedule = Schedule::factory()->create(['company_id' => $company->id]);
    $usedSchedule = Schedule::factory()->create(['company_id' => $company->id]);
    $unusedAssignment = ScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_id' => $deletableAssignmentSchedule->id,
        'effective_from' => '2026-08-01',
        'effective_to' => '2026-08-31',
    ]);
    ScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_id' => $usedSchedule->id,
        'effective_from' => '2026-08-01',
        'effective_to' => '2026-08-31',
    ]);
    $usedAssignment = ScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'effective_from' => '2026-08-01',
        'effective_to' => '2026-08-31',
    ]);

    TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $usedAssignment->worker_id,
        'occurred_local_date' => '2026-08-15',
    ]);

    app(DeleteScheduleIfUnusedAction::class)->handle($company, $unusedSchedule);
    app(DeleteScheduleAssignmentIfUnusedAction::class)->handle($company, $unusedAssignment);

    $this->assertModelMissing($unusedSchedule);
    $this->assertModelMissing($unusedAssignment);
    expect(fn () => app(DeleteScheduleIfUnusedAction::class)->handle($company, $usedSchedule))
        ->toThrow(\InvalidArgumentException::class);
    expect(fn () => app(DeleteScheduleAssignmentIfUnusedAction::class)->handle($company, $usedAssignment))
        ->toThrow(\InvalidArgumentException::class);
    $this->assertModelExists($usedSchedule);
    $this->assertModelExists($usedAssignment);
});

it('deletes mandatory rest days only inside the active company scope', function (): void {
    $company = catalogDeletionCompany();
    $otherCompany = catalogDeletionCompany();
    $restDay = MandatoryRestDay::factory()->create(['company_id' => $company->id]);
    $foreignRestDay = MandatoryRestDay::factory()->create(['company_id' => $otherCompany->id]);

    app(DeleteMandatoryRestDayIfUnusedAction::class)->handle($company, $restDay);

    $this->assertModelMissing($restDay);
    expect(fn () => app(DeleteMandatoryRestDayIfUnusedAction::class)->handle($company, $foreignRestDay))
        ->toThrow(\InvalidArgumentException::class);
    $this->assertModelExists($foreignRestDay);
});

it('deletes and blocks centers from the livewire screen using the same domain rules', function (): void {
    $company = catalogDeletionCompany();
    $user = catalogDeletionUserFor($company);
    $unusedCenter = Center::factory()->create(['company_id' => $company->id, 'name' => 'Centro libre']);
    $usedCenter = Center::factory()->create(['company_id' => $company->id, 'name' => 'Centro usado']);

    catalogDeletionBatchFor($company, $usedCenter);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('centers.index')
        ->call('deleteCenter', $unusedCenter->id)
        ->assertHasNoErrors();

    $this->assertModelMissing($unusedCenter);

    Volt::test('centers.index')
        ->call('deleteCenter', $usedCenter->id)
        ->assertHasErrors(['center']);

    $this->assertModelExists($usedCenter);
});

it('deletes company rest days from the livewire screen only when they belong to the active company', function (): void {
    $company = catalogDeletionCompany();
    $otherCompany = catalogDeletionCompany();
    $user = catalogDeletionUserFor($company);
    $restDay = MandatoryRestDay::factory()->create(['company_id' => $company->id, 'name' => 'Descanso libre']);
    $foreignRestDay = MandatoryRestDay::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Descanso ajeno']);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('mandatory-rest-days.index')
        ->call('deleteRestDay', $restDay->id)
        ->assertHasNoErrors();

    $this->assertModelMissing($restDay);

    expect(fn () => Volt::test('mandatory-rest-days.index')->call('deleteRestDay', $foreignRestDay->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    $this->assertModelExists($foreignRestDay);
});

it('deletes and blocks schedule profiles from the livewire screen using generated schedule rules', function (): void {
    $company = catalogDeletionCompany();
    $user = catalogDeletionUserFor($company);
    $unusedProfile = ScheduleProfile::factory()->create(['company_id' => $company->id, 'name' => 'Perfil libre']);
    $profileWithAssignment = ScheduleProfile::factory()->create(['company_id' => $company->id, 'name' => 'Perfil con asignacion']);
    $generatedProfile = ScheduleProfile::factory()->create(['company_id' => $company->id, 'name' => 'Perfil generado']);
    $center = Center::factory()->create(['company_id' => $company->id]);
    $batch = catalogDeletionBatchFor($company, $center);
    $relationship = catalogDeletionRelationship($company);

    ScheduleProfileAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_profile_id' => $profileWithAssignment->id,
    ]);
    $generatedAssignment = ScheduleProfileAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_profile_id' => $generatedProfile->id,
    ]);
    DailyScheduleAssignment::factory()->create([
        'company_id' => $company->id,
        'schedule_batch_id' => $batch->id,
        'employment_relationship_id' => $relationship->id,
        'source_type' => 'profile',
        'source_reference' => [
            'schedule_profile_id' => $generatedProfile->id,
            'schedule_profile_assignment_id' => $generatedAssignment->id,
        ],
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('scheduling.profiles')
        ->call('deleteProfile', $unusedProfile->id)
        ->assertHasNoErrors();

    $this->assertModelMissing($unusedProfile);

    Volt::test('scheduling.profiles')
        ->call('deleteProfile', $profileWithAssignment->id)
        ->assertHasNoErrors();

    $this->assertModelMissing($profileWithAssignment);
    $this->assertDatabaseMissing('schedule_profile_assignments', ['schedule_profile_id' => $profileWithAssignment->id]);

    Volt::test('scheduling.profiles')
        ->call('deleteProfile', $generatedProfile->id)
        ->assertHasErrors(['profile']);

    $this->assertModelExists($generatedProfile);
});
