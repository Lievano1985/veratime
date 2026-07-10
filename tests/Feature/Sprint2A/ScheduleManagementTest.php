<?php

use App\Domains\Schedules\Actions\SaveScheduleBreaksAction;
use App\Domains\Schedules\Actions\SaveScheduleDaysAction;
use App\Models\Company;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleBreak;
use App\Models\ScheduleDay;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;

it('guest cannot access schedules route', function (): void {
    $this->get(route('schedules.index'))
        ->assertRedirect(route('login'));
});

it('authenticated user without active company cannot access schedules route', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('schedules.index'))
        ->assertForbidden();
});

it('user sees only schedules from active company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = scheduleUserWithCompany($company);
    $visibleSchedule = Schedule::factory()->create([
        'company_id' => $company->id,
        'name' => 'Horario visible',
    ]);
    $foreignSchedule = Schedule::factory()->create([
        'company_id' => $otherCompany->id,
        'name' => 'Horario ajeno',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->assertSee($visibleSchedule->name)
        ->assertDontSee($foreignSchedule->name);
});

it('user does not see schedules from another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = scheduleUserWithCompany($company);
    $foreignSchedule = Schedule::factory()->create([
        'company_id' => $otherCompany->id,
        'name' => 'Horario ajeno',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->assertDontSee($foreignSchedule->name);
});

it('user can create schedule in active company', function (): void {
    $company = Company::factory()->create();
    $user = scheduleUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->set('form.code', 'MAT-01')
        ->set('form.name', 'Matutino')
        ->set('form.legal_type', 'diurnal')
        ->set('form.timezone', 'America/Mexico_City')
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('schedules', [
        'company_id' => $company->id,
        'code' => 'MAT-01',
        'name' => 'Matutino',
        'legal_type' => 'diurnal',
        'status' => 'active',
    ]);
});

it('user cannot create duplicate schedule code within same company', function (): void {
    $company = Company::factory()->create();
    $user = scheduleUserWithCompany($company);
    Schedule::factory()->create([
        'company_id' => $company->id,
        'code' => 'DUP-01',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->set('form.code', 'DUP-01')
        ->set('form.name', 'Duplicado')
        ->set('form.legal_type', 'diurnal')
        ->set('form.status', 'active')
        ->call('save')
        ->assertHasErrors(['form.code']);
});

it('schedule code can repeat in another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = scheduleUserWithCompany($company);
    Schedule::factory()->create([
        'company_id' => $otherCompany->id,
        'code' => 'SHARED-HOR',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->set('form.code', 'SHARED-HOR')
        ->set('form.name', 'Horario propio')
        ->set('form.legal_type', 'mixed')
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('schedules', [
        'company_id' => $company->id,
        'code' => 'SHARED-HOR',
    ]);
});

it('user can edit own schedule', function (): void {
    $company = Company::factory()->create();
    $user = scheduleUserWithCompany($company);
    $schedule = Schedule::factory()->create([
        'company_id' => $company->id,
        'code' => 'OLD-01',
        'name' => 'Viejo',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->call('loadEditForm', $schedule->id)
        ->set('form.code', 'NEW-01')
        ->set('form.name', 'Actualizado')
        ->set('form.legal_type', 'variable')
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('schedules', [
        'id' => $schedule->id,
        'company_id' => $company->id,
        'code' => 'NEW-01',
        'name' => 'Actualizado',
        'legal_type' => 'variable',
    ]);
});

it('blocks editing schedule from another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = scheduleUserWithCompany($company);
    $foreignSchedule = Schedule::factory()->create(['company_id' => $otherCompany->id]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    $this->expectException(ModelNotFoundException::class);

    Volt::test('schedules.index')
        ->call('loadEditForm', $foreignSchedule->id);
});

it('user can inactivate own schedule', function (): void {
    $company = Company::factory()->create();
    $user = scheduleUserWithCompany($company);
    $schedule = Schedule::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->call('inactivate', $schedule->id);

    $this->assertDatabaseHas('schedules', [
        'id' => $schedule->id,
        'status' => 'inactive',
    ]);
});

it('inactive company blocks schedule operation', function (): void {
    $company = Company::factory()->create(['status' => 'inactive']);
    $user = scheduleUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    expect($user->can('create', [Schedule::class, $company]))->toBeFalse();

    Volt::test('schedules.index')
        ->assertForbidden();
});

it('unauthorized role cannot create edit or inactivate schedules', function (): void {
    $company = Company::factory()->create();
    $user = scheduleUserWithCompany($company, 'supervisor');
    $schedule = Schedule::factory()->create([
        'company_id' => $company->id,
        'status' => 'active',
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    expect($user->can('create', [Schedule::class, $company]))->toBeFalse()
        ->and($user->can('update', $schedule))->toBeFalse()
        ->and($user->can('inactivate', $schedule))->toBeFalse();

    Volt::test('schedules.index')
        ->assertForbidden();
});

it('manipulated company id does not create schedule in another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $user = scheduleUserWithCompany($company);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->set('form.company_id', $otherCompany->id)
        ->set('form.code', 'SAFE-HOR')
        ->set('form.name', 'Horario seguro')
        ->set('form.legal_type', 'diurnal')
        ->set('form.status', 'active')
        ->call('save');

    $this->assertDatabaseHas('schedules', [
        'company_id' => $company->id,
        'code' => 'SAFE-HOR',
    ]);

    $this->assertDatabaseMissing('schedules', [
        'company_id' => $otherCompany->id,
        'code' => 'SAFE-HOR',
    ]);
});

it('user can create schedule days', function (): void {
    $company = Company::factory()->create();
    $user = scheduleUserWithCompany($company);
    $schedule = Schedule::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->call('loadEditForm', $schedule->id)
        ->set('daysForm.1.is_working_day', true)
        ->set('daysForm.1.start_time', '08:00')
        ->set('daysForm.1.end_time', '17:00')
        ->set('daysForm.1.crosses_midnight', false)
        ->call('saveDays');

    $this->assertDatabaseHas('schedule_days', [
        'company_id' => $company->id,
        'schedule_id' => $schedule->id,
        'day_of_week' => 1,
        'is_working_day' => true,
        'start_time' => '08:00',
        'end_time' => '17:00',
    ]);
});

it('does not allow duplicate day in same schedule', function (): void {
    $company = Company::factory()->create();
    $schedule = Schedule::factory()->create(['company_id' => $company->id]);

    app(SaveScheduleDaysAction::class)->handle($company, $schedule, [
        [
            'day_of_week' => 1,
            'is_working_day' => true,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'crosses_midnight' => false,
        ],
    ]);

    $this->expectException(InvalidArgumentException::class);

    app(SaveScheduleDaysAction::class)->handle($company, $schedule, [
        [
            'day_of_week' => 2,
            'is_working_day' => true,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'crosses_midnight' => false,
        ],
        [
            'day_of_week' => 2,
            'is_working_day' => true,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'crosses_midnight' => false,
        ],
    ]);
});

it('working day requires start and end time', function (): void {
    $company = Company::factory()->create();
    $user = scheduleUserWithCompany($company);
    $schedule = Schedule::factory()->create(['company_id' => $company->id]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->call('loadEditForm', $schedule->id)
        ->set('daysForm.1.is_working_day', true)
        ->set('daysForm.1.start_time', '')
        ->set('daysForm.1.end_time', '')
        ->call('saveDays')
        ->assertHasErrors([
            'daysForm.1.start_time' => 'required',
            'daysForm.1.end_time' => 'required',
        ]);
});

it('user can create scheduled break', function (): void {
    $company = Company::factory()->create();
    $user = scheduleUserWithCompany($company);
    $schedule = Schedule::factory()->create(['company_id' => $company->id]);
    $scheduleDay = ScheduleDay::factory()->create([
        'company_id' => $company->id,
        'schedule_id' => $schedule->id,
        'day_of_week' => 1,
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->call('loadEditForm', $schedule->id)
        ->set('selectedScheduleDayId', $scheduleDay->id)
        ->set('breakForm.name', 'Comida')
        ->set('breakForm.start_time', '13:00')
        ->set('breakForm.end_time', '14:00')
        ->set('breakForm.duration_minutes', 60)
        ->set('breakForm.is_paid', false)
        ->set('breakForm.is_required', true)
        ->call('saveBreak');

    $this->assertDatabaseHas('schedule_breaks', [
        'company_id' => $company->id,
        'schedule_day_id' => $scheduleDay->id,
        'name' => 'Comida',
        'duration_minutes' => 60,
        'is_required' => true,
    ]);
});

it('manipulated selected day from another schedule in same company does not show or save breaks', function (): void {
    $company = Company::factory()->create();
    $user = scheduleUserWithCompany($company);
    $schedule = Schedule::factory()->create(['company_id' => $company->id]);
    $otherSchedule = Schedule::factory()->create(['company_id' => $company->id]);
    $validDay = ScheduleDay::factory()->create([
        'company_id' => $company->id,
        'schedule_id' => $schedule->id,
        'day_of_week' => 1,
    ]);
    $otherScheduleDay = ScheduleDay::factory()->create([
        'company_id' => $company->id,
        'schedule_id' => $otherSchedule->id,
        'day_of_week' => 2,
    ]);
    ScheduleBreak::factory()->create([
        'company_id' => $company->id,
        'schedule_day_id' => $otherScheduleDay->id,
        'name' => 'Pausa ajena al horario',
        'duration_minutes' => 20,
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->call('loadEditForm', $schedule->id)
        ->set('selectedScheduleDayId', $otherScheduleDay->id)
        ->assertDontSee('Pausa ajena al horario')
        ->set('breakForm.name', 'No debe guardarse')
        ->set('breakForm.duration_minutes', 15)
        ->call('saveBreak')
        ->assertHasErrors(['selectedScheduleDayId']);

    $this->assertDatabaseMissing('schedule_breaks', [
        'schedule_day_id' => $otherScheduleDay->id,
        'name' => 'No debe guardarse',
    ]);

    $this->assertDatabaseMissing('schedule_breaks', [
        'schedule_day_id' => $validDay->id,
        'name' => 'No debe guardarse',
    ]);
});

it('negative break duration is rejected', function (): void {
    $company = Company::factory()->create();
    $user = scheduleUserWithCompany($company);
    $schedule = Schedule::factory()->create(['company_id' => $company->id]);
    $scheduleDay = ScheduleDay::factory()->create([
        'company_id' => $company->id,
        'schedule_id' => $schedule->id,
        'day_of_week' => 1,
    ]);

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

    Volt::test('schedules.index')
        ->call('loadEditForm', $schedule->id)
        ->set('selectedScheduleDayId', $scheduleDay->id)
        ->set('breakForm.duration_minutes', -5)
        ->call('saveBreak')
        ->assertHasErrors(['breakForm.duration_minutes']);
});

it('does not allow break in schedule day from another company', function (): void {
    $company = Company::factory()->create();
    $otherCompany = Company::factory()->create();
    $schedule = Schedule::factory()->create(['company_id' => $company->id]);
    $foreignSchedule = Schedule::factory()->create(['company_id' => $otherCompany->id]);
    $foreignDay = ScheduleDay::factory()->create([
        'company_id' => $otherCompany->id,
        'schedule_id' => $foreignSchedule->id,
        'day_of_week' => 1,
    ]);

    $this->expectException(InvalidArgumentException::class);

    app(SaveScheduleBreaksAction::class)->handle($company, $foreignDay, [
        [
            'name' => 'Ajena',
            'duration_minutes' => 15,
            'is_paid' => false,
            'is_required' => false,
        ],
    ]);
});

it('sprint 2a does not create time events or jornada registration', function (): void {
    expect(Schema::hasTable('schedules'))->toBeTrue()
        ->and(Schema::hasTable('schedule_days'))->toBeTrue()
        ->and(Schema::hasTable('schedule_breaks'))->toBeTrue()
        ->and(Schema::hasTable('time_events'))->toBeFalse()
        ->and(Schema::hasTable('work_days'))->toBeFalse();
});

function scheduleUserWithCompany(Company $company, string $roleKey = 'owner'): User
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
