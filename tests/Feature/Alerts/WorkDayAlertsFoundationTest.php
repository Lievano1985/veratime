<?php

namespace Tests\Feature\Alerts;

use App\Domains\Alerts\Actions\EvaluateWorkDayAlertsAction;
use App\Domains\Alerts\Actions\EvaluateWorkDayAlertsForDateRangeAction;
use App\Domains\Alerts\Actions\ResolveAlertAction;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WorkDayAlertsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluator_creates_alerts_from_active_work_day_calculation(): void
    {
        [$company, $workDay, $calculation] = $this->calculatedWorkDay([
            'overtime_minutes' => 45,
            'sunday_minutes' => 480,
            'result_snapshot' => [
                'schema_version' => 1,
                'issues' => [],
                'special_legal_cases' => [
                    'weekly_rest' => ['requires_review' => true],
                ],
            ],
        ]);

        $summary = app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);

        $this->assertSame(['created_or_updated' => 3, 'closed' => 0, 'open' => 3], $summary);
        $this->assertSame(WorkDay::STATUS_WITH_ALERTS, $workDay->refresh()->status);
        $this->assertEqualsCanonicalizing(
            ['overtime_detected', 'sunday_work', 'weekly_rest_missing'],
            AlertType::query()
                ->whereIn('id', Alert::query()->where('company_id', $company->id)->pluck('alert_type_id'))
                ->pluck('code')
                ->all(),
        );
        $this->assertSame($calculation->id, Alert::query()->where('company_id', $company->id)->firstOrFail()->work_day_calculation_id);
    }

    public function test_evaluator_does_not_duplicate_and_closes_stale_alerts(): void
    {
        [$company, $workDay, $calculation] = $this->calculatedWorkDay(['overtime_minutes' => 60]);

        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);

        $this->assertSame(1, Alert::query()->where('company_id', $company->id)->count());

        $calculation->forceFill([
            'overtime_minutes' => 0,
            'result_snapshot' => ['schema_version' => 1, 'issues' => []],
        ])->save();

        $summary = app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);

        $this->assertSame(['created_or_updated' => 0, 'closed' => 1, 'open' => 0], $summary);
        $this->assertSame(Alert::STATUS_CLOSED, Alert::query()->where('company_id', $company->id)->firstOrFail()->status);
        $this->assertSame(WorkDay::STATUS_CALCULATED, $workDay->refresh()->status);
    }

    public function test_incomplete_work_day_generates_high_alert(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay([
            'result_snapshot' => [
                'schema_version' => 1,
                'issues' => ['missing_clock_out'],
            ],
        ]);
        $workDay->forceFill(['status' => WorkDay::STATUS_UNDER_REVIEW])->save();

        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);

        $alert = Alert::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame('incomplete_work_day', $alert->alertType->code);
        $this->assertSame(AlertType::SEVERITY_HIGH, $alert->severity);
    }

    public function test_weekly_rest_missing_creates_one_alert_per_worker_week(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay([
            'result_snapshot' => [
                'schema_version' => 1,
                'issues' => [],
                'special_legal_cases' => [
                    'weekly_rest' => [
                        'requires_review' => true,
                        'worked_days_in_week' => 7,
                        'week_start' => '2026-08-03',
                        'week_end' => '2026-08-09',
                    ],
                ],
            ],
        ]);
        $start = CarbonImmutable::parse('2026-08-03');

        for ($day = 1; $day < 7; $day++) {
            $newWorkDay = WorkDay::factory()->create([
                'company_id' => $company->id,
                'worker_id' => $workDay->worker_id,
                'employment_relationship_id' => $workDay->employment_relationship_id,
                'center_id' => $workDay->center_id,
                'work_date' => $start->addDays($day)->toDateString(),
                'timezone' => 'America/Mexico_City',
                'status' => WorkDay::STATUS_CALCULATED,
            ]);
            $calculation = WorkDayCalculation::factory()->create([
                'company_id' => $company->id,
                'work_day_id' => $newWorkDay->id,
                'status' => WorkDayCalculation::STATUS_ACTIVE,
                'classification' => WorkDayCalculation::CLASSIFICATION_DIURNAL,
                'total_work_minutes' => 480,
                'ordinary_minutes' => 480,
                'overtime_minutes' => 0,
                'sunday_minutes' => 0,
                'mandatory_rest_minutes' => 0,
                'result_snapshot' => [
                    'schema_version' => 1,
                    'issues' => [],
                    'special_legal_cases' => [
                        'weekly_rest' => [
                            'requires_review' => true,
                            'worked_days_in_week' => 7,
                            'week_start' => '2026-08-03',
                            'week_end' => '2026-08-09',
                        ],
                    ],
                ],
            ]);
            $newWorkDay->forceFill(['active_calculation_id' => $calculation->id])->save();
        }

        app(EvaluateWorkDayAlertsForDateRangeAction::class)->handle($company, '2026-08-03', '2026-08-09');

        $alerts = Alert::query()
            ->where('company_id', $company->id)
            ->whereHas('alertType', fn ($query) => $query->where('code', 'weekly_rest_missing'))
            ->get();

        $this->assertCount(1, $alerts);
        $this->assertSame('2026-08-03', $alerts->first()->metadata['week_start']);
        $this->assertSame('2026-08-09', $alerts->first()->metadata['week_end']);
    }

    public function test_scheduled_absence_creates_dictaminable_incident_without_events(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay();
        $workDay->activeCalculation()->dissociate();
        $workDay->forceFill([
            'active_calculation_id' => null,
            'status' => WorkDay::STATUS_PENDING,
            'schedule_status' => WorkDay::SCHEDULE_STATUS_SCHEDULED,
            'day_type' => 'shift',
            'expected_work_minutes' => 480,
            'valid_time_event_count' => 0,
            'valid_time_event_ids' => [],
        ])->save();

        $result = app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay->refresh());

        $alert = Alert::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame(1, $result['created_or_updated']);
        $this->assertSame('scheduled_absence', $alert->rule_code);
        $this->assertSame('Falta', $alert->title);
        $this->assertSame(WorkDay::STATUS_WITH_ALERTS, $workDay->refresh()->status);
    }

    public function test_alerts_list_is_visible_to_managers_and_scoped_by_company(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay(['overtime_minutes' => 60]);
        [$otherCompany, $otherWorkDay] = $this->calculatedWorkDay(['overtime_minutes' => 60]);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);
        app(EvaluateWorkDayAlertsAction::class)->handle($otherCompany, $otherWorkDay);
        $user = $this->userForCompany($company, RoleKey::ADMIN);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('alerts.index')
            ->assertSee('Alertas')
            ->assertSee('Tiempo extra detectado');

        $this->assertSame(1, Alert::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, Alert::query()->where('company_id', $otherCompany->id)->count());
    }

    public function test_work_days_alert_badge_opens_resolution_panel_and_updates_status(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay(['overtime_minutes' => 60]);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);
        $user = $this->userForCompany($company, RoleKey::ADMIN);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('work-days.index')
            ->assertSee('Tiempo extra detectado')
            ->assertSee('Dictaminar')
            ->call('openAlertsPanel', $workDay->id)
            ->assertSet('showAlertsPanel', true)
            ->assertSee('Tiempo extra detectado')
            ->set('alertResolutionForm.status', Alert::STATUS_JUSTIFIED)
            ->set('alertResolutionForm.resolution', 'Tiempo extra autorizado por operacion.')
            ->call('resolveSelectedAlert')
            ->assertHasNoErrors()
            ->assertSet('showAlertsPanel', false);

        $alert = Alert::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame(Alert::STATUS_JUSTIFIED, $alert->status);
        $this->assertSame('Tiempo extra autorizado por operacion.', $alert->resolution);
        $this->assertSame($user->id, $alert->resolved_by);
        $this->assertNotNull($alert->resolved_at);
        $this->assertSame(WorkDay::STATUS_CALCULATED, $workDay->refresh()->status);
    }

    public function test_resolved_alert_cannot_be_resolved_again(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay(['overtime_minutes' => 60]);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);
        $user = $this->userForCompany($company, RoleKey::ADMIN);
        $alert = Alert::query()->where('company_id', $company->id)->firstOrFail();

        app(ResolveAlertAction::class)->handle($company, $alert, $user, [
            'status' => Alert::STATUS_JUSTIFIED,
            'resolution' => 'Tiempo extra autorizado por operacion.',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(ResolveAlertAction::class)->handle($company, $alert->refresh(), $user, [
            'status' => Alert::STATUS_CLOSED,
            'resolution' => 'Segundo dictamen no permitido.',
        ]);
    }

    public function test_supervisor_cannot_view_alerts_list(): void
    {
        [$company] = $this->calculatedWorkDay(['overtime_minutes' => 60]);
        $user = $this->userForCompany($company, RoleKey::SUPERVISOR);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('alerts.index')
            ->assertForbidden();
    }

    /**
     * @param array<string, mixed> $calculationOverrides
     * @return array{0: Company, 1: WorkDay, 2: WorkDayCalculation}
     */
    private function calculatedWorkDay(array $calculationOverrides = []): array
    {
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $center = Center::factory()->create(['company_id' => $company->id, 'timezone' => 'America/Mexico_City']);
        $worker = Worker::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $relationship = EmploymentRelationship::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $center->id,
            'started_at' => '2026-08-01',
            'status' => 'active',
        ]);
        $workDay = WorkDay::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $center->id,
            'work_date' => '2026-08-03',
            'timezone' => 'America/Mexico_City',
            'status' => WorkDay::STATUS_CALCULATED,
        ]);
        $calculation = WorkDayCalculation::factory()->create(array_replace_recursive([
            'company_id' => $company->id,
            'work_day_id' => $workDay->id,
            'status' => WorkDayCalculation::STATUS_ACTIVE,
            'classification' => WorkDayCalculation::CLASSIFICATION_DIURNAL,
            'total_work_minutes' => 480,
            'ordinary_minutes' => 480,
            'overtime_minutes' => 0,
            'sunday_minutes' => 0,
            'mandatory_rest_minutes' => 0,
            'result_snapshot' => [
                'schema_version' => 1,
                'issues' => [],
            ],
        ], $calculationOverrides));
        $workDay->forceFill(['active_calculation_id' => $calculation->id])->save();

        return [$company, $workDay->refresh(), $calculation];
    }

    private function userForCompany(Company $company, string $roleKey): User
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $roleKey],
            ['name' => $roleKey, 'description' => null, 'is_system' => true],
        );
        $user = User::factory()->create();
        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        return $user;
    }
}
