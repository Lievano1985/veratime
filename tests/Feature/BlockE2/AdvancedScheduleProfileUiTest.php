<?php

namespace Tests\Feature\BlockE2;

use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Domains\Scheduling\Actions\CreateScheduleProfileAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileCycleRulesAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\Role;
use App\Models\ScheduleProfile;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AdvancedScheduleProfileUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_screen_shows_advanced_options_without_legacy_names(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN_EMPRESA);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.profiles')
            ->call('openCreatePanel')
            ->assertSee('Horario fijo o rol rotativo')
            ->assertSee('Horario fijo semanal')
            ->assertSee('Rol rotativo / ciclo')
            ->assertSee('Programacion semanal manual')
            ->assertSee('Horario flexible avanzado')
            ->assertSee('Guardia avanzada')
            ->assertDontSee('Variable')
            ->assertDontSee('Rotating');
    }

    public function test_creates_cycle_profile_and_manages_cycle_days(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN_EMPRESA);
        $morning = $this->shiftTemplate($company, 'MOR', 'Manana');
        $night = $this->shiftTemplate($company, 'NIG', 'Noche', '22:00', '06:00', 0, 1);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.profiles')
            ->call('openCreatePanel')
            ->set('form.code', 'CYCLEUI')
            ->set('form.name', 'Ciclo UI')
            ->set('form.pattern_mode', 'cycle')
            ->set('cycleRules.0.shift_template_id', (string) $morning->id)
            ->call('addCycleDay')
            ->set('cycleRules.1.day_type', 'shift')
            ->set('cycleRules.1.shift_template_id', (string) $night->id)
            ->set('cycleRules.2.day_type', 'rest')
            ->call('moveCycleDay', 2, 'up')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Modelo creado.');

        $profile = ScheduleProfile::query()->where('code', 'CYCLEUI')->firstOrFail();
        $this->assertSame('pattern', $profile->profile_type);
        $this->assertSame('cycle', $profile->pattern_mode);
        $this->assertSame([1, 2, 3], $profile->cycleRules()->orderBy('cycle_day')->pluck('cycle_day')->all());
        $this->assertSame(3, $profile->cycleRules()->count());
    }

    public function test_blocks_cycle_with_less_than_two_days_and_shows_error(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN_EMPRESA);
        $template = $this->shiftTemplate($company, 'BASE', 'Base');

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.profiles')
            ->call('openCreatePanel')
            ->set('form.code', 'BADCI')
            ->set('form.name', 'Ciclo invalido')
            ->set('form.pattern_mode', 'cycle')
            ->set('cycleRules.0.shift_template_id', (string) $template->id)
            ->call('removeCycleDay', 1)
            ->call('save')
            ->assertHasErrors(['cycleRules']);
    }

    public function test_creates_flexible_profile_with_window_and_rest_cleanup(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN_EMPRESA);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.profiles')
            ->call('openCreatePanel')
            ->set('form.code', 'FLEXUI')
            ->set('form.name', 'Flexible UI')
            ->set('form.profile_type', 'flexible')
            ->set('flexibleRules.0.uses_window', true)
            ->set('flexibleRules.0.window_start_local_time', '07:00')
            ->set('flexibleRules.0.window_end_local_time', '20:00')
            ->set('flexibleRules.1.required_minutes', '450')
            ->set('flexibleRules.2.day_type', 'rest')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Modelo creado.');

        $profile = ScheduleProfile::query()->where('code', 'FLEXUI')->firstOrFail();
        $this->assertSame('flexible', $profile->profile_type);
        $this->assertSame(7, $profile->flexibleRules()->count());
        $this->assertSame(450, $profile->flexibleRules()->where('day_of_week', 2)->firstOrFail()->required_minutes);
        $rest = $profile->flexibleRules()->where('day_of_week', 3)->firstOrFail();
        $this->assertSame('rest', $rest->day_type);
        $this->assertNull($rest->required_minutes);
        $this->assertNull($rest->window_start_local_time);
    }

    public function test_creates_on_call_profile_and_does_not_present_availability_as_work(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN_EMPRESA);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.profiles')
            ->call('openCreatePanel')
            ->set('form.code', 'CALLUI')
            ->set('form.name', 'Bajo demanda UI')
            ->set('form.profile_type', 'on_call')
            ->assertSee('La disponibilidad no se contabiliza automaticamente como tiempo trabajado')
            ->assertDontSee('Trabajo esperado')
            ->set('onCallRules.5.day_type', 'rest')
            ->call('save')
            ->assertHasNoErrors();

        $profile = ScheduleProfile::query()->where('code', 'CALLUI')->firstOrFail();
        $this->assertSame('on_call', $profile->profile_type);
        $this->assertSame(7, $profile->onCallRules()->count());
        $rest = $profile->onCallRules()->where('day_of_week', 6)->firstOrFail();
        $this->assertSame('rest', $rest->day_type);
        $this->assertNull($rest->max_work_minutes);
    }

    public function test_editing_loads_configuration_and_method_change_requires_confirmation(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $admin = $this->userWithCompanyRole($company, RoleKey::ADMIN_EMPRESA);
        $template = $this->shiftTemplate($company, 'BASE', 'Base');
        $profile = app(CreateScheduleProfileAction::class)->handle($company, [
            'code' => 'EDITCI',
            'name' => 'Editar ciclo',
            'profile_type' => 'pattern',
            'pattern_mode' => 'cycle',
        ]);
        app(ReplaceScheduleProfileCycleRulesAction::class)->handle($company, $profile, [
            ['cycle_day' => 1, 'day_type' => 'shift', 'shift_template_id' => $template->id],
            ['cycle_day' => 2, 'day_type' => 'rest'],
        ]);

        $this->actingAs($admin)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.profiles')
            ->call('loadEditForm', $profile->id)
            ->assertSet('cycleRules.0.shift_template_id', (string) $template->id)
            ->set('form.profile_type', 'flexible')
            ->call('save')
            ->assertHasErrors(['confirmMethodChange'])
            ->set('confirmMethodChange', true)
            ->call('save')
            ->assertHasNoErrors();

        $profile->refresh();
        $this->assertSame('flexible', $profile->profile_type);
        $this->assertSame(0, $profile->cycleRules()->count());
        $this->assertSame(7, $profile->flexibleRules()->count());
    }

    public function test_supervisor_only_consults_and_foreign_tenant_is_blocked(): void
    {
        $company = Company::factory()->create(['status' => 'active']);
        $otherCompany = Company::factory()->create(['status' => 'active']);
        $center = Center::factory()->for($company)->create(['status' => 'active']);
        $supervisor = $this->userWithCompanyRole($company, RoleKey::SUPERVISOR);
        $otherProfile = app(CreateScheduleProfileAction::class)->handle($otherCompany, [
            'code' => 'OTHER',
            'name' => 'Otro tenant',
            'profile_type' => 'calendar',
        ]);
        $visibleProfile = app(CreateScheduleProfileAction::class)->handle($company, [
            'code' => 'VISIBLE',
            'name' => 'Visible',
            'profile_type' => 'calendar',
        ]);
        app(AssignOperationalScopeAction::class)->handle($company, $supervisor, ['effective_from' => now()->toDateString()], center: $center);

        $this->actingAs($supervisor)->withSession(['current_company_id' => $company->id]);

        Volt::test('scheduling.profiles')
            ->assertSee('Visible')
            ->assertDontSee('Nuevo modelo')
            ->call('showDetail', $visibleProfile->id)
            ->assertSee('Solo consulta')
            ->call('showDetail', $otherProfile->id)
            ->assertForbidden();
    }

    public function test_e2_keeps_daily_core_tables_without_future_operational_modules(): void
    {
        $this->assertTrue(Schema::hasTable('schedule_batches'));
        $this->assertTrue(Schema::hasTable('daily_schedule_assignments'));
        $this->assertTrue(Schema::hasTable('daily_schedule_segments'));
        $this->assertFalse(Schema::hasTable('on_call_activations'));
        $this->assertTrue(Schema::hasTable('work_days'));
        $this->assertTrue(Schema::hasTable('work_day_calculations'));
    }

    private function shiftTemplate(
        Company $company,
        string $code,
        string $name,
        string $start = '08:00',
        string $end = '16:00',
        int $startOffset = 0,
        int $endOffset = 0,
    ): ShiftTemplate {
        return app(CreateShiftTemplateAction::class)->handle($company, [
            'code' => $code,
            'name' => $name,
            'status' => 'active',
        ], [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => $start, 'end_local_time' => $end, 'start_day_offset' => $startOffset, 'end_day_offset' => $endOffset, 'sort_order' => 1],
        ]);
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
