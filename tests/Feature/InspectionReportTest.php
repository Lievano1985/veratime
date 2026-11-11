<?php

use App\Models\Company;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use App\Support\RoleKey;
use Database\Seeders\ProductSeeder;

it('previews and exports historical unit attendance for the active company only', function () {
    $this->seed(ProductSeeder::class);
    $company = Company::factory()->create();
    $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
    $user = User::factory()->create(['status' => 'active']);
    $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
    $worker = Worker::factory()->for($company)->create(['full_name' => 'Persona visible']);
    $foreign = Worker::factory()->create(['full_name' => 'Persona de otra empresa']);
    $day = WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $worker->id]);
    WorkDay::factory()->create(['company_id' => $foreign->company_id, 'worker_id' => $foreign->id]);
    $unit = OrganizationalUnit::factory()->forCompany($company)->create();
    EmploymentUnitAssignment::query()->forceCreate([
        'company_id' => $company->id,
        'employment_relationship_id' => $day->employment_relationship_id,
        'organizational_unit_id' => $unit->id,
        'effective_from' => '2026-08-01',
        'effective_to' => '2026-08-05',
        'status' => 'inactive',
        'assignment_type' => 'primary',
        'source' => 'manual',
    ]);
    $filters = ['from' => '2026-08-01', 'to' => '2026-08-07'];
    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    $this->get(route('reports.index'))->assertOk()->assertSee('Vista previa');
    $this->get(route('reports.inspection', $filters + ['scope' => 'company']))
        ->assertOk()->assertSee('Persona visible')->assertDontSee('Persona de otra empresa');
    $this->get(route('reports.inspection', $filters + ['scope' => 'unit', 'unit' => $unit->id]))
        ->assertOk()->assertSee('Persona visible')->assertSee('Pendiente');
    TimeEvent::factory()->create([
        'company_id' => $company->id,
        'worker_id' => $worker->id,
        'occurred_local_date' => '2026-08-03',
        'event_type' => 'break_start',
        'source' => 'admin_manual',
        'status' => 'voided',
        'metadata' => ['reason' => 'Captura corregida por RH'],
        'void_reason' => 'Pausa duplicada',
    ]);
    $preview = $this->get(route('reports.inspection', $filters + ['scope' => 'worker', 'worker' => $worker->id]))
        ->assertOk()->assertSee('Inicio de pausa')->assertSee('Captura corregida por RH')->assertSee('Pausa duplicada');
    $cut = $preview->viewData('cut');
    TimeEvent::query()->where('company_id', $company->id)->update(['void_reason' => 'Cambio posterior al corte']);
    $xlsx = $this->get(route('reports.inspection', ['cut' => $cut]));
    $xlsx->assertDownload('expediente-jornada.xlsx');
    expect($xlsx->streamedContent())->toStartWith('PK');
    $this->assertStringContainsString('Pausa duplicada', \Illuminate\Support\Facades\Cache::get("inspection-report:{$company->id}:{$user->id}:{$cut}")['eventRows']->first()[14]);

    $this->post(route('reports.issue'), ['cut' => $cut])->assertRedirect(route('reports.issued', $cut));
    $this->post(route('reports.issue'), ['cut' => $cut])->assertRedirect(route('reports.issued', $cut));
    $this->assertDatabaseCount('issued_attendance_reports', 1);
    \Illuminate\Support\Facades\Cache::forget("inspection-report:{$company->id}:{$user->id}:{$cut}");
    $this->get(route('reports.issued', $cut))
        ->assertOk()->assertSee('Pausa duplicada')->assertDontSee('Cambio posterior al corte');
    $this->get(route('reports.index', ['tab' => 'history']))->assertOk()->assertSee($cut);
    $issuedXlsx = $this->get(route('reports.issued', ['folio' => $cut, 'format' => 'xlsx']));
    $issuedXlsx->assertDownload('expediente-jornada.xlsx');
    expect($issuedXlsx->streamedContent())->toStartWith('PK');

    $this->getJson(route('reports.inspection', $filters + ['scope' => 'worker', 'worker' => $foreign->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('worker');

    $user->companies()->attach($foreign->company_id, ['role_id' => $role->id, 'status' => 'active']);
    $this->withSession(['current_company_id' => $foreign->company_id])
        ->get(route('reports.issued', $cut))->assertNotFound();
});

it('shows the full recorded week around the selected day and exports the weekly cut', function () {
    $this->seed(ProductSeeder::class);
    $company = Company::factory()->create();
    $user = User::factory()->create(['status' => 'active', 'global_role' => RoleKey::SUPER_ADMIN]);
    $worker = Worker::factory()->for($company)->create();
    foreach (['2026-08-03', '2026-08-05'] as $date) {
        $day = WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $worker->id, 'work_date' => $date]);
        $calculation = WorkDayCalculation::factory()->create([
            'company_id' => $company->id, 'work_day_id' => $day->id, 'classification' => 'diurnal',
            'ordinary_minutes' => 480, 'overtime_minutes' => 45,
            'overtime_double_minutes' => 30, 'overtime_triple_minutes' => 15,
        ]);
        $day->update(['active_calculation_id' => $calculation->id]);
    }

    $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
    $preview = $this->get(route('reports.inspection', [
        'from' => '2026-08-05', 'to' => '2026-08-05', 'scope' => 'worker', 'worker' => $worker->id, 'report' => 'weekly',
    ]))->assertOk()->assertSee('Cumplimiento semanal de jornada')->assertSee('Contexto semanal');

    expect(array_slice($preview->viewData('rows')->sole(), 4, 4))->toBe([960, 60, 30, 2]);
    expect($preview->viewData('eventRows')->pluck(1)->all())->toBe(['2026-08-03', '2026-08-05']);
    $xlsx = $this->get(route('reports.inspection', ['cut' => $preview->viewData('cut')]))
        ->assertDownload('cumplimiento-semanal.xlsx');
    expect($xlsx->streamedContent())->toStartWith('PK');
});
