<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDay;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user->companies()->attach($company, [
            'status' => 'active',
            'is_default' => true,
            'role_id' => $role->id,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response
            ->assertOk()
            ->assertSee('Dashboard Vera Time')
            ->assertDontSee('Filtrar tablero')
            ->assertSee('Operaci&oacute;n de la jornada', false)
            ->assertSee('Cobertura de asistencia')
            ->assertSee('Incidencias pendientes')
            ->assertSee('Cumplimiento laboral')
            ->assertSee('Personal con más incidencias en 30 días')
            ->assertDontSee('Atención de jornadas')
            ->assertSee('No hay incidencias pendientes por atender.')
            ->assertDontSee('Indicadores semanales de acumulaci&oacute;n', false)
            ->assertDontSee('Cumplimiento por semana')
            ->assertDontSee('Salidas anticipadas');
    }

    public function test_authenticated_users_without_active_company_cannot_visit_the_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response->assertForbidden();
    }

    public function test_compliance_week_selector_changes_only_compliance_period_and_keeps_company_and_center_scope(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-21 12:00', 'America/Mexico_City'));
        [$company, $user] = $this->dashboardUser();
        $relationship = EmploymentRelationship::factory()->forCompany($company)->create(['started_at' => '2026-01-01']);
        $attributes = ['company_id' => $company->id, 'employment_relationship_id' => $relationship->id,
            'worker_id' => $relationship->worker_id, 'center_id' => $relationship->center_id, 'expected_work_minutes' => 480];
        WorkDay::factory()->create(array_replace($attributes, ['work_date' => '2026-10-05']));
        WorkDay::factory()->create(array_replace($attributes, ['work_date' => '2026-10-12']));
        $second = EmploymentRelationship::factory()->forCompany($company)->create(['center_id' => $relationship->center_id]);
        WorkDay::factory()->create(array_replace($attributes, ['employment_relationship_id' => $second->id, 'worker_id' => $second->worker_id, 'work_date' => '2026-10-05']));
        $outside = EmploymentRelationship::factory()->forCompany($company)->create();
        WorkDay::factory()->create(array_replace($attributes, ['employment_relationship_id' => $outside->id, 'worker_id' => $outside->worker_id, 'center_id' => $outside->center_id, 'work_date' => '2026-10-05']));
        $foreign = EmploymentRelationship::factory()->create();
        WorkDay::factory()->create(['company_id' => $foreign->company_id, 'employment_relationship_id' => $foreign->id, 'worker_id' => $foreign->worker_id, 'center_id' => $foreign->center_id, 'work_date' => '2010-01-04']);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        $filters = ['date' => '2026-10-21', 'center' => $relationship->center_id];
        $baseline = $this->get(route('dashboard', $filters))->assertOk()->viewData('summary');
        $response = $this->get(route('dashboard', $filters + ['compliance_week' => '2026-10-05']))->assertOk()
            ->assertSee('Semana de cumplimiento')->assertSee('Semana 41 · 05/10/2026 – 11/10/2026');
        $summary = $response->viewData('summary');
        $this->assertSame('2026-10-21', $summary['date']);
        $this->assertSame($baseline['metrics'], $summary['metrics']);
        $this->assertSame($baseline['workspace']['completed'], $summary['workspace']['completed']);
        $this->assertSame('2026-10-05', $summary['labor_compliance']['week_start']);
        $this->assertSame('2026-10-11', $summary['labor_compliance']['week_end']);
        $this->assertSame(2, $summary['labor_compliance']['total_workers']);
        $this->assertSame(1, $baseline['labor_compliance']['total_workers']);
        $this->assertNotContains('2010-01-04', array_column($summary['labor_compliance']['week_options'], 'value'));
    }

    public function test_compliance_week_selector_handles_iso_year_boundary_and_rejects_unfinished_or_invalid_weeks(): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-07 12:00', 'America/Mexico_City'));
        [$company, $user] = $this->dashboardUser();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        $this->get(route('dashboard', ['compliance_week' => '2026-12-28']))->assertOk()
            ->assertSee('Semana 53 · 28/12/2026 – 03/01/2027')
            ->assertViewHas('summary', fn ($summary) => $summary['labor_compliance']['week_end'] === '2027-01-03');
        foreach (['2027-01-04', '2026-12-29', 'invalid'] as $invalidWeek) {
            $this->get(route('dashboard', ['compliance_week' => $invalidWeek]))->assertSessionHasErrors('compliance_week');
        }
    }

    private function dashboardUser(): array
    {
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user->companies()->attach($company, ['status' => 'active', 'is_default' => true, 'role_id' => $role->id]);

        return [$company, $user];
    }
}
