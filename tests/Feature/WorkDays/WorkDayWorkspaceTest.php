<?php

namespace Tests\Feature\WorkDays;

use App\Domains\Alerts\Actions\ConfirmWeeklyRestReviewAction;
use App\Domains\Alerts\Actions\EvaluateWorkDayAlertsAction;
use App\Domains\Alerts\Actions\ResolveAlertAction;
use App\Domains\Alerts\Actions\ResolveScheduledAbsenceAlertAsAttendanceIncidentAction;
use App\Domains\Dashboard\Actions\BuildOperationalDashboardAction;
use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Domains\WorkDays\Actions\BuildWorkDayWorkspaceAction;
use App\Domains\WorkDays\Actions\CloseDailyIncidentFollowUpAction;
use App\Domains\WorkDays\Actions\RecalculateSelectedWorkDayAction;
use App\Domains\WorkDays\Actions\ReclassifyWorkDayWeekAction;
use App\Domains\WorkDays\Support\WorkDayWorkflow;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\AttendanceIncident;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\LegalParameter;
use App\Models\Role;
use App\Models\ScheduleBatch;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Database\Seeders\AlertTypeSeeder;
use Database\Seeders\LegalRuleSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WorkDayWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-14 20:00', 'America/Mexico_City'));
        $this->seed([LegalRuleSeeder::class, AlertTypeSeeder::class, ProductSeeder::class]);
    }

    public function test_daily_cases_take_priority_over_weekly_cases(): void
    {
        [$company, $user, $day] = $this->fixture();
        $weekly = $this->alert($day, 'weekly_overtime_exceeded');
        $daily = $this->alert($day, 'overtime_detected');
        $weekly->update(['detected_at' => now()->addMinute()]);
        $cases = $this->workspace($company, $user)['incident_cases']->getCollection();

        $this->assertSame($daily->id, $cases->first()['alert']->id);
        $this->assertFalse($cases->first()['weekly']);
        $this->assertSame($weekly->id, $cases->last()['alert']->id);
    }

    public function test_weekly_rest_confirmation_reuses_daily_reviews_without_comment_and_preserves_evidence(): void
    {
        [$company, $user, $day] = $this->fixture();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00', 'America/Mexico_City'));
        foreach (range(12, 18) as $date) {
            if ($date === 14) {
                continue;
            }
            $other = WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $day->worker_id,
                'employment_relationship_id' => $day->employment_relationship_id, 'center_id' => $day->center_id,
                'work_date' => '2026-10-'.$date, 'day_type' => $date >= 17 ? 'rest' : 'shift']);
            $calc = WorkDayCalculation::factory()->create(['company_id' => $company->id, 'work_day_id' => $other->id,
                'status' => 'active', 'total_work_minutes' => 480, 'ordinary_minutes' => 480, 'overtime_minutes' => 0,
                'result_snapshot' => ['issues' => []]]);
            $other->update(['active_calculation_id' => $calc->id]);
            if ($date >= 17) {
                $rest = $this->alert($other, 'scheduled_rest_work');
                app(ResolveAlertAction::class)->handle($company, $rest, $user, ['status' => Alert::STATUS_JUSTIFIED, 'resolution' => 'Descanso trabajado autorizado.']);
            }
        }
        $weekly = $this->alert($day, 'weekly_rest_missing');
        $before = $day->activeCalculation->getAttributes();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        Volt::test('work-days.index')->call('openIncident', $weekly->id)
            ->assertSee('Confirmar revisión semanal')->assertDontSee('Comentario obligatorio')
            ->assertSee('Días trabajados')->assertSee('Días sin trabajo registrado')
            ->assertDontSee('1. Revisar la jornada')->assertDontSee('Ver detalle técnico del cálculo')
            ->assertDontSee('Extra total del día')->assertDontSee('Sin dictamen')
            ->assertSee('Descanso trabajado autorizado.')
            ->call('confirmWeeklyRestReview')->assertHasNoErrors()->assertSee('Revisado: semana sin descanso');
        $this->assertSame(Alert::STATUS_CLOSED, $weekly->fresh()->status);
        $this->assertSame($user->id, $weekly->fresh()->resolved_by);
        $this->assertCount(2, $weekly->fresh()->metadata['resolution']['daily_reviews']);
        $this->assertSame($before, $day->fresh()->activeCalculation->getAttributes());
        $summary = $this->workspace($company, $user);
        $this->assertSame(0, $summary['incident_cases']->total());
    }

    public function test_weekly_rest_confirmation_rejects_pending_daily_reviews(): void
    {
        [$company, $user, $day] = $this->fixture();
        $weekly = $this->alert($day, 'weekly_rest_missing');
        $this->alert($day, 'scheduled_rest_work');
        $this->expectException(\InvalidArgumentException::class);
        app(ConfirmWeeklyRestReviewAction::class)->handle($company, $weekly, $user);
    }

    public function test_extra_hours_do_not_make_a_complete_day_pending(): void
    {
        [$company, $user, $day] = $this->fixture();
        $this->alert($day, 'overtime_detected');
        $summary = $this->workspace($company, $user);
        $this->assertSame(1, $summary['completed']);
        $this->assertSame(0, $summary['pending']);
        $this->assertSame(1, $summary['incidents']->total());
        $this->alert($day, 'late_arrival_detected');
        $summary = $this->workspace($company, $user);
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(0, $summary['incidents']->total());
    }

    public function test_day_detail_explains_additional_minutes_inside_configured_review_margin(): void
    {
        [$company, $user, $day] = $this->fixture();
        $day->activeCalculation->update(['total_work_minutes' => 485, 'ordinary_minutes' => 480, 'overtime_minutes' => 5,
            'result_snapshot' => ['issues' => [], 'ordinary_overtime' => ['daily_limit_minutes' => 480]]]);
        LegalParameter::query()->create([
            'company_id' => $company->id, 'code' => 'overtime_review_margin_minutes',
            'value' => ['minutes' => 10], 'effective_from' => '2026-10-12', 'status' => 'active',
        ]);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        Volt::test('work-days.index')->call('openAlertsPanel', $day->id)
            ->assertSee('Tiempo adicional dentro del margen: 5 min de 10 min configurados.')
            ->assertSee('sin autorización automática')->assertSee('8 h 5 min');
    }

    public function test_weekly_panel_separates_ordinary_extra_and_total_worked_time(): void
    {
        [$company, $user, $day] = $this->fixture();
        $day->activeCalculation->update(['total_work_minutes' => 1080, 'ordinary_minutes' => 480, 'overtime_minutes' => 600,
            'result_snapshot' => ['issues' => [], 'ordinary_overtime' => ['weekly_limit_minutes' => 2880]]]);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $day);
        $alert = $day->alerts()->where('rule_code', 'weekly_overtime_exceeded')->firstOrFail();
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        Volt::test('work-days.index')->call('selectWorkspaceTab', 'incidencias')->call('openIncident', $alert->id)
            ->assertSee('Horas extra semanales superiores al límite')
            ->assertSee('Total trabajado (sin duplicar conceptos)')->assertSee('Acumulado en días laborables')
            ->assertSee('Referencia semanal de jornada')->assertSee('Exceso del aviso seleccionado')
            ->assertSee('Trabajar una jornada en descanso no la convierte automáticamente en extra.');
    }

    public function test_week_reclassification_preserves_previous_versions_and_other_workers(): void
    {
        [$company, $user, $day] = $this->fixture();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 12:00', 'America/Mexico_City'));
        $day->update(['day_type' => 'rest']);
        $day->activeCalculation->update(['total_work_minutes' => 480, 'ordinary_minutes' => 120, 'overtime_minutes' => 360]);
        $previous = $day->activeCalculation->fresh();
        $previousAttributes = $previous->getAttributes();
        $unrelated = WorkDay::factory()->create(['company_id' => $company->id, 'work_date' => '2026-10-14']);
        $unrelatedCalc = WorkDayCalculation::factory()->create(['company_id' => $company->id, 'work_day_id' => $unrelated->id]);
        $unrelated->update(['active_calculation_id' => $unrelatedCalc->id]);
        $beforeOther = $unrelatedCalc->fresh()->getAttributes();
        $results = app(ReclassifyWorkDayWeekAction::class)->handle($company, $day, $user);
        $this->assertCount(1, $results);
        $next = $results->first()->activeCalculation;
        $this->assertNotSame($previous->id, $next->id);
        $this->assertSame($previous->version + 1, $next->version);
        $this->assertSame(480, $next->total_work_minutes);
        $this->assertSame(480, $next->ordinary_minutes);
        $this->assertSame(0, $next->overtime_minutes);
        $this->assertSame($user->id, $next->generated_by_id);
        $this->assertSame(360, $previous->fresh()->overtime_minutes);
        $this->assertSame(120, $previous->fresh()->ordinary_minutes);
        $this->assertSame(WorkDayCalculation::STATUS_SUPERSEDED, $previous->fresh()->status);
        $preserved = $previous->fresh()->getAttributes();
        unset($previousAttributes['status'], $previousAttributes['updated_at'], $preserved['status'], $preserved['updated_at']);
        $this->assertSame($previousAttributes, $preserved);
        $this->assertSame($beforeOther, $unrelatedCalc->fresh()->getAttributes());
        $this->assertSame(480, data_get($next->result_snapshot, 'special_legal_cases.scheduled_rest.worked_minutes'));
    }

    public function test_clearing_attendance_opens_the_calculated_case_without_reopening_attendance(): void
    {
        [$company, $user, $day] = $this->fixture();
        $day->activeCalculation->update(['result_snapshot' => ['issues' => ['missing_clock_out']]]);
        $day->update(['status' => WorkDay::STATUS_UNDER_REVIEW]);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $day);
        $this->assertFalse(WorkDayWorkflow::isCompleted($day));
        $day->activeCalculation->update(['result_snapshot' => ['issues' => []]]);
        $day->update(['status' => WorkDay::STATUS_CALCULATED]);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $day->fresh());
        $summary = $this->workspace($company, $user);
        $this->assertSame(1, $summary['completed']);
        $this->assertGreaterThan(0, $summary['open_incidents']);
        $this->assertFalse(Alert::where('work_day_id', $day->id)->where('rule_code', 'incomplete_work_day')->whereIn('status', Alert::OPEN_STATUSES)->exists());
    }

    public function test_comment_cannot_complete_missing_markings(): void
    {
        [$company, $user, $day] = $this->fixture();
        $day->activeCalculation->update(['result_snapshot' => ['issues' => ['missing_clock_out']]]);
        $alert = $this->alert($day, 'incomplete_work_day');
        $this->expectException(\InvalidArgumentException::class);
        app(ResolveAlertAction::class)->handle($company, $alert, $user, ['status' => Alert::STATUS_JUSTIFIED, 'resolution' => 'Comentario administrativo']);
    }

    public function test_empty_calculation_without_documented_absence_does_not_complete_a_work_day(): void
    {
        [$company, $user, $day] = $this->fixture();
        $day->activeCalculation->update(['total_work_minutes' => 0, 'ordinary_minutes' => 0, 'overtime_minutes' => 0, 'result_snapshot' => ['issues' => []]]);
        $this->assertFalse(WorkDayWorkflow::isCompleted($day));
        $this->assertSame(1, $this->workspace($company, $user)['pending']);
    }

    public function test_review_is_preserved_for_same_calculation_and_versioned_if_result_changes(): void
    {
        [$company, $user, $day] = $this->fixture();
        $evaluate = app(EvaluateWorkDayAlertsAction::class);
        $evaluate->handle($company, $day);
        $alert = Alert::where('work_day_id', $day->id)->where('rule_code', 'overtime_detected')->firstOrFail();
        app(ResolveAlertAction::class)->handle($company, $alert, $user, ['status' => Alert::STATUS_JUSTIFIED, 'resolution' => 'Autorización administrativa registrada']);
        $evaluate->handle($company, $day->fresh());
        $this->assertSame(Alert::STATUS_JUSTIFIED, $alert->fresh()->status);
        $day->activeCalculation->update(['overtime_minutes' => 180]);
        $evaluate->handle($company, $day->fresh());
        $this->assertSame(Alert::STATUS_NEW, $alert->fresh()->status);
        $this->assertSame('Autorización administrativa registrada', $alert->fresh()->metadata['resolution_history'][0]['previous_resolution']);
    }

    public function test_prevention_separates_eight_actual_extra_hours_from_three_future_days(): void
    {
        [$company, $user, $day] = $this->fixture();
        $day->activeCalculation->update(['ordinary_minutes' => 1440, 'overtime_minutes' => 480, 'total_work_minutes' => 1920]);
        $batch = ScheduleBatch::factory()->create(['company_id' => $company->id, 'center_id' => $day->center_id, 'period_start' => '2026-10-12', 'period_end' => '2026-10-18', 'status' => 'published']);
        foreach (['2026-10-15', '2026-10-16', '2026-10-17'] as $date) {
            DailyScheduleAssignment::factory()->create(['company_id' => $company->id, 'schedule_batch_id' => $batch->id,
                'employment_relationship_id' => $day->employment_relationship_id,
                'work_date' => $date, 'day_type' => 'shift', 'required_minutes' => 480]);
        }
        $summary = $this->workspace($company, $user);
        $person = $summary['prevention']['people'][0];
        $this->assertSame(480, $person['overtime_minutes']);
        $this->assertSame(60, $person['overtime_margin_minutes']);
        $this->assertSame(3, $person['remaining_days']);
        $this->assertSame(1440, $person['planned_minutes']);
        $this->assertSame(3360, $person['projected_total_minutes']);
        $this->assertFalse($person['already_exceeded']);
    }

    public function test_workspace_never_includes_another_company_and_renders_three_tabs(): void
    {
        [$company, $user, $day] = $this->fixture();
        [$foreign, $otherUser, $otherDay] = $this->fixture();
        $this->alert($day, 'overtime_detected');
        $this->alert($otherDay, 'overtime_detected');
        $summary = $this->workspace($company, $user);
        $this->assertSame(1, $summary['total']);
        $this->assertSame([$day->worker_id], $summary['incidents']->pluck('worker_id')->all());
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        Volt::test('work-days.index')->set('dateFrom', '2026-10-12')->set('dateTo', '2026-10-14')
            ->assertSee('Prevención')->assertSee('Jornadas finalizadas')
            ->call('selectWorkspaceTab', 'incidencias')->assertSee('Incidencias calculadas')
            ->assertSee($day->worker->full_name)->assertDontSee($otherDay->worker->full_name)
            ->call('openIncident', $summary['incidents']->first()->id)->assertSet('showAlertsPanel', true);
    }

    public function test_confirmed_absence_is_finalized_as_an_absence_without_worked_hours(): void
    {
        [$company, $user, $day] = $this->fixture();
        $day->update(['active_calculation_id' => null, 'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED, 'day_type' => 'shift', 'expected_work_minutes' => 480,
            'valid_time_event_count' => 0, 'valid_time_event_ids' => []]);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $day->fresh());
        $alert = Alert::where('work_day_id', $day->id)->where('rule_code', 'scheduled_absence')->firstOrFail();
        $incident = app(ResolveScheduledAbsenceAlertAsAttendanceIncidentAction::class)->handle($company, $alert, $user,
            ['status' => Alert::STATUS_CORRECTED, 'resolution' => 'Ausencia confirmada por RH con evidencia']);
        $this->assertSame(AttendanceIncident::TYPE_UNJUSTIFIED_ABSENCE, $incident->incident_type);
        $this->assertSame(0, $day->fresh()->activeCalculation->total_work_minutes);
        $this->assertTrue(WorkDayWorkflow::isCompleted($day->fresh()));
    }

    public function test_weekly_case_appears_once_when_filtering_another_day_in_the_same_week(): void
    {
        [$company, $user, $day] = $this->fixture();
        $day->update(['work_date' => '2026-10-12']);
        $alert = $this->alert($day, 'weekly_overtime_exceeded');
        $summary = app(BuildWorkDayWorkspaceAction::class)->handle($company, $user,
            ['date_from' => '2026-10-14', 'date_to' => '2026-10-14']);
        $this->assertSame([$alert->id], $summary['incidents']->pluck('id')->all());
        $this->assertTrue($summary['incidents']->first()->provisional);
        $this->assertSame('12/10/2026 – 18/10/2026', $summary['incidents']->first()->case_period);
    }

    public function test_workspace_scope_is_enforced_even_without_caller_supplied_center_filters(): void
    {
        [$company, $manager, $day] = $this->fixture();
        $this->alert($day, 'overtime_detected');
        $outside = EmploymentRelationship::factory()->forCompany($company)->create();
        $other = WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $outside->worker_id,
            'employment_relationship_id' => $outside->id, 'center_id' => $outside->center_id,
            'work_date' => '2026-10-14', 'expected_work_minutes' => 480]);
        $operator = User::factory()->create(['status' => 'active']);
        $role = Role::firstOrCreate(['key' => RoleKey::RH_OPERATIVO], ['name' => 'RH operativo']);
        $operator->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active']);
        app(AssignOperationalScopeAction::class)->handle($company, $operator, ['effective_from' => '2026-10-01'], center: $day->center);
        $summary = $this->workspace($company, $operator);
        $this->assertSame(1, $summary['total']);
        $this->assertSame([$day->worker_id], $summary['incidents']->pluck('worker_id')->all());
        $this->assertFalse($operator->can('view', $other));
    }

    public function test_prevention_does_not_invent_a_projection_without_future_schedule(): void
    {
        [$company, $user, $day] = $this->fixture();
        $day->activeCalculation->update(['overtime_minutes' => 480]);
        $person = $this->workspace($company, $user)['prevention']['people'][0];
        $this->assertSame(0, $person['remaining_days']);
        $this->assertNull($person['projected_total_minutes']);
        $day->activeCalculation->update(['overtime_minutes' => 600]);
        $this->assertSame([], $this->workspace($company, $user)['prevention']['people']);
    }

    public function test_dashboard_and_workspace_share_the_same_completion_and_review_counts(): void
    {
        [$company, $user, $day] = $this->fixture();
        $this->alert($day, 'overtime_detected');
        $workspace = $this->workspace($company, $user);
        $dashboard = app(BuildOperationalDashboardAction::class)->handle($company, $user, '2026-10-14');
        $this->assertSame($workspace['completed'], $dashboard['workspace']['completed']);
        $this->assertSame($workspace['pending'], $dashboard['workspace']['pending']);
        $this->assertSame($workspace['open_incidents'], $dashboard['workspace']['open_incidents']);
        $this->assertSame($workspace['completed'], $dashboard['metrics']['completed_work_days']);
    }

    public function test_daily_case_groups_all_notices_and_keeps_reviewed_overtime_visible(): void
    {
        [$company, $user, $day] = $this->fixture();
        $extra = $this->alert($day, 'overtime_detected');
        app(ResolveAlertAction::class)->handle($company, $extra, $user, ['status' => Alert::STATUS_JUSTIFIED, 'resolution' => 'Horas extra autorizadas']);
        $this->alert($day, 'twelve_hours_exceeded');
        $this->alert($day, 'daily_limit_exceeded');
        $this->alert($day, 'weekly_overtime_exceeded');
        $cases = $this->workspace($company, $user)['incident_cases'];
        $this->assertSame(2, $cases->total());
        $daily = $cases->getCollection()->firstWhere('weekly', false);
        $this->assertSame(3, $daily['alerts']->count());
        $this->assertSame(2, $daily['pending']);
        $this->assertTrue($daily['alerts']->contains('id', $extra->id));
        $reviewed = app(BuildWorkDayWorkspaceAction::class)->handle($company, $user, ['date_from' => '2026-10-14', 'date_to' => '2026-10-14'], 'reviewed', false);
        $this->assertSame(0, $reviewed['incident_cases']->total());
    }

    public function test_sidebar_guides_individual_review_without_bulk_closing(): void
    {
        [$company, $user, $day] = $this->fixture();
        $extra = $this->alert($day, 'overtime_detected');
        $daily = $this->alert($day, 'daily_limit_exceeded');
        $pause = $this->alert($day, 'minimum_break_missing');
        $weekly = $this->alert($day, 'weekly_overtime_exceeded');
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        Volt::test('work-days.index')->call('openIncident', $pause->id)
            ->assertSeeInOrder(['1. Revisar la jornada', '2. Corregir y actualizar si es necesario', '3. Atender cada pendiente', '4. Consultar antecedentes'])
            ->assertSee('Horas programadas')->assertSee('Marcajes válidos actuales')
            ->assertSee('Ver detalle técnico del cálculo')->assertSee('Extra total del día')
            ->assertDontSee('Cerrar seguimiento diario')
            ->assertSee('Si faltan marcajes de pausa')
            ->set('alertResolutionForm.status', Alert::STATUS_CLOSED)
            ->set('alertResolutionForm.resolution', 'Se confirmó que no hubo pausa y se registró la medida correctiva.')
            ->call('resolveSelectedAlert')->assertHasNoErrors()
            ->assertSet('alertResolutionForm.resolution', '')
            ->assertSet('alertResolutionForm.status', Alert::STATUS_CLOSED);
        $this->assertSame(Alert::STATUS_CLOSED, $pause->fresh()->status);
        $this->assertSame(Alert::STATUS_NEW, $daily->fresh()->status);
        $this->assertSame(Alert::STATUS_NEW, $extra->fresh()->status);
        $this->assertSame(Alert::STATUS_NEW, $weekly->fresh()->status);
        $this->assertSame(600, $day->fresh()->activeCalculation->total_work_minutes);
    }

    public function test_daily_follow_up_rejects_incomplete_attendance_and_does_not_close_notices(): void
    {
        [$company, $user, $day] = $this->fixture();
        $daily = $this->alert($day, 'daily_limit_exceeded');
        $this->alert($day, 'incomplete_work_day');
        try {
            app(CloseDailyIncidentFollowUpAction::class)->handle($company, $day, $user, 'No se puede completar con comentario.');
            $this->fail('Debe rechazar la jornada incompleta.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame(Alert::STATUS_NEW, $daily->fresh()->status);
        }
    }

    public function test_daily_follow_up_cannot_modify_another_company(): void
    {
        [$company, $user] = $this->fixture();
        [$foreign, $foreignUser, $day] = $this->fixture();
        $daily = $this->alert($day, 'daily_limit_exceeded');
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        try {
            app(CloseDailyIncidentFollowUpAction::class)->handle($company, $day, $user, 'No debe guardar en otra empresa.');
            $this->fail('Debe rechazar la jornada de otra empresa.');
        } catch (AuthorizationException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
        $this->assertSame(Alert::STATUS_NEW, $daily->fresh()->status);
    }

    public function test_consultation_permission_does_not_allow_closing_follow_up(): void
    {
        [$company, $manager, $day] = $this->fixture();
        $daily = $this->alert($day, 'daily_limit_exceeded');
        $viewer = User::factory()->create(['status' => 'active']);
        $role = Role::firstOrCreate(['key' => RoleKey::SUPERVISOR], ['name' => 'Supervisor']);
        $viewer->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active']);
        app(AssignOperationalScopeAction::class)->handle($company, $viewer, ['effective_from' => '2026-10-01'], center: $day->center);
        $this->assertTrue($viewer->can('view', $day));
        $this->assertFalse($viewer->can('recalculate', $day));
        try {
            app(RecalculateSelectedWorkDayAction::class)->handle($company, $day, $viewer);
            $this->fail('El permiso de consulta no permite recalcular.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(1, $day->calculations()->count());
        }
        try {
            app(CloseDailyIncidentFollowUpAction::class)->handle($company, $day, $viewer, 'Consulta sin permiso para cerrar avisos.');
            $this->fail('El permiso de consulta no permite cerrar.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(Alert::STATUS_NEW, $daily->fresh()->status);
        }
    }

    public function test_sidebar_recalculates_selected_day_immediately_and_does_not_change_other_workers(): void
    {
        [$company, $user, $day] = $this->fixture();
        $oldCalculation = $day->activeCalculation;
        $oldAlert = $this->alert($day, 'twelve_hours_exceeded');
        $otherRelationship = EmploymentRelationship::factory()->forCompany($company)->create(['center_id' => $day->center_id]);
        $other = WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $otherRelationship->worker_id,
            'employment_relationship_id' => $otherRelationship->id, 'center_id' => $day->center_id, 'work_date' => '2026-10-14']);
        $otherCalculation = WorkDayCalculation::factory()->create(['company_id' => $company->id, 'work_day_id' => $other->id]);
        $other->update(['active_calculation_id' => $otherCalculation->id]);
        $otherAlert = $this->alert($other, 'twelve_hours_exceeded');
        $before = $otherCalculation->fresh()->getAttributes();
        foreach (['07:00' => 'clock_in', '14:00' => 'break_start', '15:00' => 'break_end', '16:00' => 'clock_out'] as $time => $type) {
            TimeEvent::factory()->create(['company_id' => $company->id, 'worker_id' => $day->worker_id,
                'employment_relationship_id' => $day->employment_relationship_id, 'center_id' => $day->center_id,
                'status' => 'valid', 'event_type' => $type, 'timezone' => 'America/Mexico_City',
                'occurred_local_date' => '2026-10-14', 'occurred_local_time' => $time,
                'occurred_at_utc' => CarbonImmutable::parse('2026-10-14 '.$time, 'America/Mexico_City')->utc()]);
        }
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        Volt::test('work-days.index')->set('dateFrom', '2026-10-14')->set('dateTo', '2026-10-14')
            ->call('openIncident', $oldAlert->id)->assertSee('Recalcular esta jornada')
            ->call('recalculateSelectedWorkDay')->assertHasNoErrors()->assertSet('showAlertsPanel', true)
            ->assertSee('Jornada actualizada:');
        $updated = $day->fresh()->activeCalculation;
        $this->assertSame(480, $updated->total_work_minutes);
        $this->assertSame(60, $updated->break_minutes);
        $this->assertSame($user->id, $updated->generated_by_id);
        $this->assertSame(WorkDayCalculation::GENERATED_BY_USER, $updated->generated_by_type);
        $this->assertSame(WorkDayCalculation::STATUS_SUPERSEDED, $oldCalculation->fresh()->status);
        $this->assertSame(Alert::STATUS_CLOSED, $oldAlert->fresh()->status);
        $this->assertSame($before, $otherCalculation->fresh()->getAttributes());
        $this->assertSame(Alert::STATUS_NEW, $otherAlert->fresh()->status);
        $this->assertSame($otherCalculation->id, $other->fresh()->active_calculation_id);
    }

    public function test_individual_recalculation_keeps_an_invalid_break_sequence_under_review(): void
    {
        [$company, $user, $day] = $this->fixture();
        foreach (['07:00' => 'clock_in', '14:00' => 'break_start', '15:00' => 'break_start', '20:00' => 'clock_out'] as $time => $type) {
            TimeEvent::factory()->create(['company_id' => $company->id, 'worker_id' => $day->worker_id,
                'employment_relationship_id' => $day->employment_relationship_id, 'center_id' => $day->center_id,
                'status' => 'valid', 'event_type' => $type, 'timezone' => 'America/Mexico_City',
                'occurred_local_date' => '2026-10-14', 'occurred_local_time' => $time,
                'occurred_at_utc' => CarbonImmutable::parse('2026-10-14 '.$time, 'America/Mexico_City')->utc()]);
        }
        $updated = app(RecalculateSelectedWorkDayAction::class)->handle($company, $day, $user);
        $this->assertNotEmpty($updated->activeCalculation->result_snapshot['issues']);
        $this->assertFalse(WorkDayWorkflow::isCompleted($updated));
        $this->assertTrue($updated->alerts->where('rule_code', 'incomplete_work_day')->whereIn('status', Alert::OPEN_STATUSES)->isNotEmpty());
    }

    public function test_individual_recalculation_rejects_foreign_company_and_preserves_empty_unscheduled_history(): void
    {
        [$company, $user, $day] = $this->fixture();
        [$foreign, $foreignUser, $foreignDay] = $this->fixture();
        try {
            app(RecalculateSelectedWorkDayAction::class)->handle($company, $foreignDay, $user);
            $this->fail('No debe recalcular otra empresa.');
        } catch (AuthorizationException $exception) {
            $this->assertSame(1, $foreignDay->calculations()->count());
        }
        $day->update(['schedule_status' => WorkDay::SCHEDULE_STATUS_UNSCHEDULED, 'metadata' => ['source' => 'time_events']]);
        try {
            app(RecalculateSelectedWorkDayAction::class)->handle($company, $day, $user);
            $this->fail('Debe conservar la jornada vacía para revisión.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertModelExists($day);
            $this->assertSame(1, $day->calculations()->count());
        }
    }

    public function test_recalculation_history_is_separate_from_current_notices_in_list_and_sidebar(): void
    {
        [$company, $user, $day] = $this->fixture();
        $day->activeCalculation->update(['total_work_minutes' => 660, 'ordinary_minutes' => 480, 'overtime_minutes' => 180,
            'break_minutes' => 120, 'result_snapshot' => ['issues' => [], 'ordinary_overtime' => ['daily_limit_minutes' => 480]]]);
        $old = collect(['twelve_hours_exceeded', 'daily_overtime_over_three_hours', 'minimum_break_missing'])
            ->map(fn ($rule) => $this->alert($day, $rule));
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $day);
        $case = $this->workspace($company, $user)['incident_cases']->getCollection()->firstWhere('weekly', false);
        $this->assertSame(2, $case['pending']);
        $this->assertSame(2, $case['current_alerts']->count());
        $this->assertSame(3, $case['historical_alerts']->count());
        $this->assertEqualsCanonicalizing($old->pluck('id')->all(), $case['historical_alerts']->pluck('id')->all());
        $this->assertTrue($old->every(fn ($alert) => $alert->fresh()->isClosedByRecalculation()));
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        Volt::test('work-days.index')->set('dateFrom', '2026-10-14')->set('dateTo', '2026-10-14')
            ->call('selectWorkspaceTab', 'incidencias')->assertSee('Ver 2 avisos vigentes')->assertSee('Ver historial (3)')
            ->assertDontSee('Ver 6 avisos')->assertSee('Ya no aplica tras recálculo')
            ->call('openIncident', $case['alert']->id)
            ->assertViewHas('selectedWorkDay', fn ($selected) => $selected->alerts->count() === 2 && $selected->historicalAlerts->count() === 3)
            ->assertSee('Cierre automático:');
        $this->assertSame(5, $day->alerts()->count());
    }

    public function test_manual_follow_up_remains_current_and_is_not_mislabeled_as_recalculation_history(): void
    {
        [$company, $user, $day] = $this->fixture();
        $alert = $this->alert($day, 'daily_limit_exceeded');
        app(ResolveAlertAction::class)->handle($company, $alert, $user, ['status' => Alert::STATUS_CLOSED, 'resolution' => 'Se revisó el exceso con el supervisor.']);
        $summary = app(BuildWorkDayWorkspaceAction::class)->handle($company, $user, ['date_from' => '2026-10-14', 'date_to' => '2026-10-14'], 'reviewed', false);
        $case = $summary['incident_cases']->first();
        $this->assertSame(0, $case['pending']);
        $this->assertSame(1, $case['current_alerts']->count());
        $this->assertSame(0, $case['historical_alerts']->count());
        $this->assertFalse($alert->fresh()->isClosedByRecalculation());
    }

    public function test_case_with_only_automatic_closures_remains_available_as_history(): void
    {
        [$company, $user, $day] = $this->fixture();
        $alert = $this->alert($day, 'weekly_overtime_exceeded');
        $alert->update(['status' => Alert::STATUS_CLOSED, 'resolution' => 'Cerrada automáticamente por recálculo semanal.', 'resolved_at' => now()]);
        $this->assertTrue($alert->fresh()->isClosedByRecalculation());
        $summary = app(BuildWorkDayWorkspaceAction::class)->handle($company, $user, ['date_from' => '2026-10-14', 'date_to' => '2026-10-14'], 'reviewed', false);
        $case = $summary['incident_cases']->first();
        $this->assertTrue($case['weekly']);
        $this->assertSame(0, $case['current_alerts']->count());
        $this->assertSame(1, $case['historical_alerts']->count());
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        Volt::test('work-days.index')->set('dateFrom', '2026-10-14')->set('dateTo', '2026-10-14')
            ->call('selectWorkspaceTab', 'incidencias')->set('reviewState', 'reviewed')
            ->assertSee('Sin avisos vigentes')->assertSee('Ver historial (1)')
            ->call('openIncident', $alert->id)
            ->assertViewHas('selectedWorkDay', fn ($selected) => $selected->alerts->isEmpty() && $selected->historicalAlerts->count() === 1)
            ->assertDontSee('Guardar revisión');
    }

    public function test_scheduled_rest_work_can_be_evaluated_separately_with_audited_authorization_and_no_change_to_worked_time(): void
    {
        [$company, $user, $day] = $this->fixture();
        $day->update(['day_type' => 'rest', 'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED]);
        $rest = $this->alert($day, 'scheduled_rest_work');
        $daily = $this->alert($day, 'long_work_day');
        $beforeCalculation = $day->activeCalculation->getAttributes();
        $this->assertSame(1, app(CloseDailyIncidentFollowUpAction::class)->handle($company, $day, $user, 'Se revisó el aviso de jornada larga.'));
        $this->assertSame(Alert::STATUS_CLOSED, $daily->fresh()->status);
        $this->assertSame(Alert::STATUS_NEW, $rest->fresh()->status);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        $component = Volt::test('work-days.index')->set('dateFrom', '2026-10-14')->set('dateTo', '2026-10-14')
            ->call('openIncident', $rest->id)->assertSee('Evaluación del trabajo en descanso')
            ->assertSee('Confirmar trabajo en descanso autorizado')->assertSee('Confirmar trabajo en descanso sin autorización')
            ->assertSee('Guardar evaluación')->assertDontSee('Cerrar seguimiento diario')
            ->call('resolveSelectedAlert')->assertHasErrors(['alertResolutionForm.resolution' => 'required'])
            ->set('alertResolutionForm.status', Alert::STATUS_JUSTIFIED)
            ->set('alertResolutionForm.resolution', 'Se confirmó autorización del supervisor para trabajar durante el descanso.')
            ->call('resolveSelectedAlert')->assertHasNoErrors()->assertSee('Trabajo en descanso autorizado');
        $this->assertSame(Alert::STATUS_JUSTIFIED, $rest->fresh()->status);
        $this->assertSame($user->id, $rest->fresh()->resolved_by);
        $this->assertNotNull($rest->fresh()->resolved_at);
        $component->set('alertResolutionForm.status', Alert::STATUS_CORRECTED)
            ->set('alertResolutionForm.resolution', 'Tras revisar la evidencia se confirmó que no existía autorización.')
            ->call('resolveSelectedAlert')->assertHasNoErrors()->assertSee('Trabajo en descanso sin autorización')
            ->assertSet('alertResolutionForm.resolution', '')
            ->assertSet('alertResolutionForm.status', Alert::STATUS_JUSTIFIED);
        $this->assertSame(Alert::STATUS_CORRECTED, $rest->fresh()->status);
        $this->assertSame(Alert::STATUS_JUSTIFIED, $rest->fresh()->metadata['resolution_history'][0]['previous_status']);
        $this->assertSame($beforeCalculation, $day->fresh()->activeCalculation->getAttributes());
        $this->assertSame('rest', $day->fresh()->day_type);
    }

    public function test_confirmed_lateness_is_shown_as_a_confirmed_incident_instead_of_a_normal_day(): void
    {
        [$company, $user, $day] = $this->fixture();
        $day->activeCalculation->update(['late_arrival_minutes' => 25]);
        $late = $this->alert($day, 'late_arrival_detected');
        app(ResolveAlertAction::class)->handle($company, $late, $user, ['status' => Alert::STATUS_CORRECTED, 'resolution' => 'Se confirmó que entró tarde.']);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        $component = Volt::test('work-days.index')->set('dateFrom', '2026-10-14')->set('dateTo', '2026-10-14')
            ->assertSee('Retardo confirmado')->assertSee('Finalizada con aclaración')->assertSee('Ver jornada')
            ->assertViewHas('workDays', fn ($days) => $days->first()->attendance_completed === true);
        $this->assertMatchesRegularExpression('/bg-amber-100[^>]*>\s*Retardo confirmado\s*<\/span>/', $component->html());
        app(ResolveAlertAction::class)->handle($company, $late->fresh(), $user, ['status' => Alert::STATUS_JUSTIFIED, 'resolution' => 'Se recibió evidencia para justificar el retardo.']);
        Volt::test('work-days.index')->set('dateFrom', '2026-10-14')->set('dateTo', '2026-10-14')
            ->assertSee('Retardo justificado')->assertSee('Finalizada con aclaración');
        $this->assertSame(25, $day->fresh()->activeCalculation->late_arrival_minutes);
    }

    private function fixture(): array
    {
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::firstOrCreate(['key' => RoleKey::ADMIN_EMPRESA], ['name' => 'Administrador']);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $relationship = EmploymentRelationship::factory()->forCompany($company)->create(['started_at' => '2026-10-01']);
        $day = WorkDay::factory()->create(['company_id' => $company->id, 'worker_id' => $relationship->worker_id,
            'employment_relationship_id' => $relationship->id, 'center_id' => $relationship->center_id,
            'work_date' => '2026-10-14', 'status' => WorkDay::STATUS_WITH_ALERTS, 'valid_time_event_count' => 4]);
        $calculation = WorkDayCalculation::factory()->create(['company_id' => $company->id, 'work_day_id' => $day->id,
            'classification' => WorkDayCalculation::CLASSIFICATION_DIURNAL, 'total_work_minutes' => 600,
            'ordinary_minutes' => 480, 'overtime_minutes' => 120, 'result_snapshot' => ['issues' => []]]);
        $day->update(['active_calculation_id' => $calculation->id]);

        return [$company, $user, $day->fresh()];
    }

    private function alert(WorkDay $day, string $rule): Alert
    {
        return Alert::create(['company_id' => $day->company_id, 'alert_type_id' => AlertType::where('code', $rule)->firstOrFail()->id,
            'work_day_id' => $day->id, 'worker_id' => $day->worker_id, 'rule_code' => $rule, 'severity' => 'warning',
            'status' => Alert::STATUS_NEW, 'title' => $rule, 'detected_at' => now(), 'fingerprint' => (string) Str::uuid()]);
    }

    private function workspace(Company $company, User $user): array
    {
        return app(BuildWorkDayWorkspaceAction::class)->handle($company, $user, ['date_from' => '2026-10-12', 'date_to' => '2026-10-14']);
    }
}
