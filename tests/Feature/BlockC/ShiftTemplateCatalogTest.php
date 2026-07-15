<?php

namespace Tests\Feature\BlockC;

use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\InactivateShiftTemplateAction;
use App\Domains\Scheduling\Actions\ReactivateShiftTemplateAction;
use App\Domains\Scheduling\Actions\UpdateShiftTemplateAction;
use App\Domains\Scheduling\Actions\ValidateShiftTemplateSegmentsAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\Role;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ShiftTemplateCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_template_schema_keeps_catalog_company_scoped_without_legacy_fields(): void
    {
        $this->assertTrue(Schema::hasTable('shift_templates'));
        $this->assertTrue(Schema::hasTable('shift_template_segments'));
        $this->assertFalse(Schema::hasColumn('shift_templates', 'center_id'));
        $this->assertFalse(Schema::hasColumn('shift_templates', 'timezone'));
        $this->assertFalse(Schema::hasColumn('shift_templates', 'profile_type'));
        $this->assertFalse(Schema::hasColumn('shift_templates', 'legal_type'));
        $this->assertFalse(Schema::hasColumn('shift_templates', 'worker_id'));
        $this->assertFalse(Schema::hasColumn('shift_templates', 'required_minutes'));
    }

    public function test_company_managers_create_shift_templates_and_supervisor_does_not_administer(): void
    {
        $company = Company::factory()->create(['status' => 'active']);

        foreach ([RoleKey::OWNER, RoleKey::ADMIN, RoleKey::RH] as $index => $roleKey) {
            $user = $this->userWithCompanyRole($company, $roleKey);
            $this->assertTrue(Gate::forUser($user)->allows('create', [ShiftTemplate::class, $company]));

            app(CreateShiftTemplateAction::class)->handle($company, [
                'code' => 'TUR-'.$index,
                'name' => 'Turno '.$roleKey,
            ], $this->simpleWorkSegments());
        }

        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);

        $this->assertFalse(Gate::forUser($supervisor)->allows('create', [ShiftTemplate::class, $company]));
        $this->assertDatabaseCount('shift_templates', 3);
    }

    public function test_supervisor_needs_active_operational_scope_to_consult_active_templates(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $center = Center::factory()->for($company)->create(['status' => 'active']);
        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);

        app(CreateShiftTemplateAction::class)->handle($company, ['code' => 'ACT', 'name' => 'Activo'], $this->simpleWorkSegments());
        app(CreateShiftTemplateAction::class)->handle($company, ['code' => 'INA', 'name' => 'Inactivo', 'status' => 'inactive'], $this->simpleWorkSegments());

        $this->actingAs($supervisor)->withSession(['current_company_id' => $company->id]);
        $this->get(route('scheduling.shifts'))->assertForbidden();

        app(AssignOperationalScopeAction::class)->handle($company, $supervisor, ['effective_from' => now()->toDateString()], center: $center);

        $this->get(route('scheduling.shifts'))
            ->assertOk()
            ->assertSee('Activo')
            ->assertDontSee('Inactivo')
            ->assertSee('Solo consulta')
            ->assertDontSee('Nueva plantilla');
    }

    public function test_code_is_unique_per_company_and_same_code_is_allowed_in_another_company(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $otherCompany = Company::factory()->create(['status' => 'active']);

        app(CreateShiftTemplateAction::class)->handle($company, ['code' => 'APER', 'name' => 'Apertura'], $this->simpleWorkSegments());
        app(CreateShiftTemplateAction::class)->handle($otherCompany, ['code' => 'APER', 'name' => 'Apertura externa'], $this->simpleWorkSegments());

        $this->expectException(InvalidArgumentException::class);
        app(CreateShiftTemplateAction::class)->handle($company, ['code' => 'aper', 'name' => 'Duplicado'], $this->simpleWorkSegments());
    }

    public function test_blocks_cross_tenant_updates_and_manipulated_company_context(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $otherCompany = Company::factory()->create(['status' => 'active']);
        $template = app(CreateShiftTemplateAction::class)->handle($otherCompany, ['code' => 'EXT', 'name' => 'Externa'], $this->simpleWorkSegments());

        $this->expectException(InvalidArgumentException::class);
        app(UpdateShiftTemplateAction::class)->handle($company, $template, ['code' => 'BAD', 'name' => 'No permitido'], $this->simpleWorkSegments());
    }

    public function test_valid_segments_support_simple_night_split_fixed_break_and_duration_break(): void
    {
        $company = Company::factory()->create(['status' => 'active']);

        $night = app(CreateShiftTemplateAction::class)->handle($company, ['code' => 'NOCT', 'name' => 'Nocturno'], [[
            'segment_type' => 'work',
            'timing_mode' => 'fixed',
            'start_local_time' => '22:00',
            'end_local_time' => '06:00',
            'start_day_offset' => 0,
            'end_day_offset' => 1,
            'sort_order' => 1,
        ]]);

        $split = app(CreateShiftTemplateAction::class)->handle($company, ['code' => 'PART', 'name' => 'Partido'], [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '13:00', 'sort_order' => 1],
            ['segment_type' => 'break', 'timing_mode' => 'fixed', 'start_local_time' => '13:00', 'end_local_time' => '15:00', 'is_paid' => false, 'sort_order' => 2],
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '15:00', 'end_local_time' => '18:00', 'sort_order' => 3],
            ['segment_type' => 'break', 'timing_mode' => 'duration', 'duration_minutes' => 30, 'is_paid' => true, 'sort_order' => 4],
        ]);

        $this->assertTrue($night->metrics()['crosses_midnight']);
        $this->assertSame(480, $night->metrics()['work_minutes']);
        $this->assertSame(480, $split->metrics()['work_minutes']);
        $this->assertSame(120, $split->metrics()['fixed_break_minutes']);
        $this->assertSame(30, $split->metrics()['paid_break_minutes']);
        $this->assertSame(600, $split->metrics()['total_span_minutes']);
        $this->assertSame(2, $split->metrics()['work_segment_count']);
    }

    public function test_invalid_segment_rules_are_blocked(): void
    {
        $validator = app(ValidateShiftTemplateSegmentsAction::class);

        foreach ($this->invalidSegments() as $segments) {
            try {
                $validator->handle($segments);
                $this->fail('Segmentos invalidos aceptados.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_update_is_atomic_and_keeps_previous_segments_when_new_segments_are_invalid(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $template = app(CreateShiftTemplateAction::class)->handle($company, ['code' => 'BASE', 'name' => 'Base'], $this->simpleWorkSegments());

        try {
            app(UpdateShiftTemplateAction::class)->handle($company, $template, ['code' => 'BASE', 'name' => 'Base modificada'], [
                ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '12:00', 'sort_order' => 1],
                ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '11:00', 'end_local_time' => '15:00', 'sort_order' => 2],
            ]);
        } catch (InvalidArgumentException) {
            // Expected.
        }

        $template->refresh()->load('segments');
        $this->assertSame('Base', $template->name);
        $this->assertCount(1, $template->segments);
        $this->assertSame('08:00:00', (string) $template->segments->first()->start_local_time);
    }

    public function test_inactivation_and_valid_reactivation_are_non_destructive(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $template = app(CreateShiftTemplateAction::class)->handle($company, ['code' => 'BASE', 'name' => 'Base'], $this->simpleWorkSegments());

        app(InactivateShiftTemplateAction::class)->handle($company, $template);
        $this->assertSame('inactive', $template->refresh()->status);
        $this->assertCount(1, $template->segments);

        app(ReactivateShiftTemplateAction::class)->handle($company, $template);
        $this->assertSame('active', $template->refresh()->status);
    }

    public function test_shift_templates_ui_creates_edits_filters_and_shows_preview(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.shifts')
            ->call('openCreatePanel')
            ->set('form.code', 'PART')
            ->set('form.name', 'Jornada partida')
            ->set('segments.0.start_local_time', '08:00')
            ->set('segments.0.end_local_time', '13:00')
            ->call('addSegment')
            ->set('segments.1.segment_type', 'break')
            ->set('segments.1.timing_mode', 'fixed')
            ->set('segments.1.start_local_time', '13:00')
            ->set('segments.1.end_local_time', '14:00')
            ->set('segments.1.is_paid', false)
            ->call('addSegment')
            ->set('segments.2.segment_type', 'work')
            ->set('segments.2.start_local_time', '14:00')
            ->set('segments.2.end_local_time', '17:00')
            ->call('save')
            ->assertHasNoErrors();

        $template = ShiftTemplate::query()->where('code', 'PART')->firstOrFail();

        Volt::test('scheduling.shifts')
            ->set('filters.search', 'PART')
            ->assertSee('Jornada partida')
            ->assertSee('8 h')
            ->call('showDetail', $template->id)
            ->assertSee('Detalle de segmentos')
            ->assertSee('08:00')
            ->call('loadEditForm', $template->id)
            ->set('form.name', 'Jornada partida editada')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('shift_templates', [
            'company_id' => $company->id,
            'code' => 'PART',
            'name' => 'Jornada partida editada',
        ]);
    }

    public function test_shift_templates_ui_blocks_guest_without_company_and_foreign_tenant(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $otherCompany = Company::factory()->create(['status' => 'active']);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);
        $orphanUser = User::factory()->create(['status' => 'active']);
        $foreignTemplate = app(CreateShiftTemplateAction::class)->handle($otherCompany, ['code' => 'EXT', 'name' => 'Externo'], $this->simpleWorkSegments());

        $this->get(route('scheduling.shifts'))->assertRedirect(route('login'));

        $this->actingAs($orphanUser);
        $this->get(route('scheduling.shifts'))->assertForbidden();

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Volt::test('scheduling.shifts')
            ->call('loadEditForm', $foreignTemplate->id);
    }

    public function test_sidebar_hides_catalog_for_unauthorized_roles_and_form_does_not_expose_company_id(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $payroll = $this->userWithCompanyRole($company, RoleKey::PAYROLL);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN);

        $this->actingAs($payroll)->withSession(['current_company_id' => $company->id]);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('CatÃ¡logo de turnos');

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Organización')
            ->assertSee('Horarios')
            ->assertSee('Catálogo de turnos')
            ->assertDontSee('Horarios legacy')
            ->assertDontSee('Asignacion de Horarios');

        $this->get(route('scheduling.shifts'))
            ->assertOk()
            ->assertSee('Catálogo de turnos')
            ->assertDontSee('company_id');
    }

    public function test_block_c_does_not_create_future_wfm_or_calculation_tables(): void
    {
        foreach (['schedule_profiles', 'schedule_batches', 'daily_schedule_assignments', 'daily_schedule_segments', 'work_days', 'work_day_calculations'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} no debe existir en Bloque C.");
        }
    }

    private function simpleWorkSegments(): array
    {
        return [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '16:00', 'sort_order' => 1],
        ];
    }

    private function invalidSegments(): array
    {
        return [
            [['segment_type' => 'work', 'timing_mode' => 'duration', 'duration_minutes' => 480, 'sort_order' => 1]],
            [['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => null, 'end_local_time' => null, 'sort_order' => 1]],
            [['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '17:00', 'end_local_time' => '08:00', 'sort_order' => 1]],
            [['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '09:00', 'start_day_offset' => 2, 'sort_order' => 1]],
            [['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '09:00', 'start_day_offset' => 1, 'end_day_offset' => 0, 'sort_order' => 1]],
            [
                ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '20:00', 'sort_order' => 1],
                ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '19:00', 'end_local_time' => '21:00', 'sort_order' => 2],
            ],
            [
                ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '12:00', 'sort_order' => 1],
                ['segment_type' => 'break', 'timing_mode' => 'duration', 'duration_minutes' => 30, 'sort_order' => 1],
            ],
            [['segment_type' => 'break', 'timing_mode' => 'duration', 'duration_minutes' => 30, 'sort_order' => 1]],
            [['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '00:00', 'end_local_time' => '00:30', 'start_day_offset' => 1, 'end_day_offset' => 1, 'sort_order' => 1]],
        ];
    }

    private function userWithCompanyRole(Company $company, string $roleKey): User
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $roleKey],
            ['name' => strtoupper($roleKey), 'description' => 'Rol prueba', 'is_system' => true],
        );
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return $user->refresh();
    }
}
