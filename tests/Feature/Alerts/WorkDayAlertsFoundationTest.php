<?php

namespace Tests\Feature\Alerts;

use App\Domains\Alerts\Actions\EvaluateWorkDayAlertsAction;
use App\Domains\Alerts\Actions\EvaluateWorkDayAlertsForDateRangeAction;
use App\Domains\Alerts\Actions\ResolveAlertAction;
use App\Domains\Alerts\Actions\ResolveScheduledAbsenceAlertAsAttendanceIncidentAction;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\AttendanceIncident;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\LegalParameter;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Database\Seeders\AlertTypeSeeder;
use Database\Seeders\LegalRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WorkDayAlertsFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LegalRuleSeeder::class);
    }

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

    public function test_evaluator_creates_punctuality_alerts_from_calculated_minutes(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay([
            'late_arrival_minutes' => 12,
            'early_departure_minutes' => 8,
        ]);

        $summary = app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);

        $this->assertSame(['created_or_updated' => 2, 'closed' => 0, 'open' => 2], $summary);
        $this->assertSame(WorkDay::STATUS_WITH_ALERTS, $workDay->refresh()->status);
        $this->assertEqualsCanonicalizing(
            ['late_arrival_detected', 'early_departure_detected'],
            Alert::query()
                ->where('company_id', $company->id)
                ->pluck('rule_code')
                ->all(),
        );
        $this->assertSame(12, Alert::query()->where('rule_code', 'late_arrival_detected')->firstOrFail()->metadata['late_arrival_minutes']);
        $this->assertSame(8, Alert::query()->where('rule_code', 'early_departure_detected')->firstOrFail()->metadata['early_departure_minutes']);
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

    public function test_extended_day_has_three_tasks_and_retires_duplicate_alerts_without_changing_calculation(): void
    {
        [$company, $day, $calculation] = $this->calculatedWorkDay([
            'total_work_minutes' => 845, 'overtime_minutes' => 395, 'break_minutes' => 0,
            'result_snapshot' => ['issues' => [], 'ordinary_overtime' => ['daily_limit_minutes' => 450]],
        ]);
        $this->seed(AlertTypeSeeder::class);
        $old = [];
        foreach (['twelve_hours_exceeded', 'long_work_day', 'daily_overtime_over_three_hours'] as $rule) {
            $type = AlertType::where('code', $rule)->firstOrFail();
            $old[] = Alert::create([
                'company_id' => $company->id, 'worker_id' => $day->worker_id,
                'work_day_id' => $day->id, 'work_day_calculation_id' => $calculation->id,
                'alert_type_id' => $type->id, 'rule_code' => $rule,
                'fingerprint' => 'old-'.$rule.'-'.$day->id, 'severity' => 'warning',
                'status' => Alert::STATUS_NEW, 'title' => $rule, 'description' => 'Aviso anterior', 'detected_at' => now(),
            ]);
        }
        $before = $calculation->fresh()->getAttributes();
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $day);
        $this->assertEqualsCanonicalizing(['overtime_detected', 'daily_limit_exceeded', 'minimum_break_missing'],
            $day->alerts()->whereIn('status', Alert::OPEN_STATUSES)->pluck('rule_code')->all());
        foreach ($old as $alert) {
            $this->assertTrue($alert->fresh()->isClosedByRecalculation());
        }
        $this->assertSame($before, $calculation->fresh()->getAttributes());
    }

    public function test_sunday_is_reviewed_daily_and_legacy_weekly_duplicate_is_retired(): void
    {
        [$company, $day, $calculation] = $this->calculatedWorkDay([
            'sunday_minutes' => 480,
            'result_snapshot' => ['issues' => [], 'special_legal_cases' => ['scheduled_rest' => ['worked' => true]]],
        ]);
        $this->seed(AlertTypeSeeder::class);
        $type = AlertType::where('code', 'weekly_sunday_work')->firstOrFail();
        $legacy = Alert::create([
            'company_id' => $company->id, 'worker_id' => $day->worker_id,
            'work_day_id' => $day->id, 'work_day_calculation_id' => $calculation->id,
            'alert_type_id' => $type->id, 'rule_code' => 'weekly_sunday_work',
            'fingerprint' => 'legacy-sunday-'.$day->id, 'severity' => 'informational',
            'status' => Alert::STATUS_NEW, 'title' => 'Domingos trabajados en la semana',
            'description' => 'Resumen anterior', 'detected_at' => now(),
            'metadata' => ['week_start' => '2026-08-03', 'week_end' => '2026-08-09'],
        ]);
        $before = $calculation->fresh()->getAttributes();
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $day);
        $this->assertTrue($legacy->fresh()->isClosedByRecalculation());
        $this->assertEqualsCanonicalizing(['sunday_work', 'scheduled_rest_work'],
            $day->alerts()->whereIn('status', Alert::OPEN_STATUSES)->pluck('rule_code')->all());
        $this->assertSame($before, $calculation->fresh()->getAttributes());
    }

    public function test_evaluator_creates_new_daily_alerts_from_legal_snapshots_and_worker_age(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay([
            'classification' => WorkDayCalculation::CLASSIFICATION_NOCTURNAL,
            'total_work_minutes' => 601,
            'overtime_minutes' => 181,
            'break_minutes' => 0,
            'result_snapshot' => [
                'schema_version' => 1,
                'issues' => [],
                'ordinary_overtime' => ['daily_limit_minutes' => 420, 'weekly_limit_minutes' => 2880],
                'special_legal_cases' => ['scheduled_rest' => ['worked' => true]],
            ],
        ]);
        $workDay->worker()->update(['birth_date' => '2011-08-03']);

        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay->refresh());

        $this->assertEqualsCanonicalizing([
            'overtime_detected',
            'daily_limit_exceeded',
            'minimum_break_missing',
            'scheduled_rest_work',
            'minor_daily_hours_exceeded',
            'minor_restricted_work',
        ], Alert::query()->where('company_id', $company->id)->pluck('rule_code')->all());
    }

    public function test_evaluator_creates_weekly_accumulation_alerts_once_per_worker_week(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay([
            'total_work_minutes' => 601,
            'ordinary_minutes' => 481,
            'overtime_minutes' => 120,
            'break_minutes' => 30,
            'result_snapshot' => [
                'schema_version' => 1,
                'issues' => [],
                'ordinary_overtime' => ['daily_limit_minutes' => 480, 'weekly_limit_minutes' => 2880],
            ],
        ]);

        foreach ([1, 2, 3, 4, 6] as $offset) {
            $day = WorkDay::factory()->create([
                'company_id' => $company->id,
                'worker_id' => $workDay->worker_id,
                'employment_relationship_id' => $workDay->employment_relationship_id,
                'center_id' => $workDay->center_id,
                'work_date' => CarbonImmutable::parse('2026-08-03')->addDays($offset)->toDateString(),
                'timezone' => 'America/Mexico_City',
                'status' => WorkDay::STATUS_CALCULATED,
            ]);
            $calculation = WorkDayCalculation::factory()->create([
                'company_id' => $company->id,
                'work_day_id' => $day->id,
                'status' => WorkDayCalculation::STATUS_ACTIVE,
                'classification' => WorkDayCalculation::CLASSIFICATION_DIURNAL,
                'total_work_minutes' => 600,
                'ordinary_minutes' => 480,
                'overtime_minutes' => 120,
                'break_minutes' => 30,
                'sunday_minutes' => $offset === 6 ? 600 : 0,
                'result_snapshot' => [
                    'schema_version' => 1,
                    'issues' => [],
                    'ordinary_overtime' => ['daily_limit_minutes' => 480, 'weekly_limit_minutes' => 2880],
                ],
            ]);
            $day->forceFill(['active_calculation_id' => $calculation->id])->save();
        }

        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay->refresh());
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay->refresh());

        $weekly = Alert::query()
            ->where('company_id', $company->id)
            ->whereIn('rule_code', [
                'weekly_hours_exceeded',
                'weekly_overtime_exceeded',
                'weekly_overtime_days_exceeded',
                'weekly_sunday_work',
            ])
            ->get();

        $this->assertCount(3, $weekly);
        $this->assertSame(3, $weekly->unique('fingerprint')->count());
        $this->assertSame('2026-08-03', $weekly->first()->metadata['week_start']);
        $ordinary = $weekly->firstWhere('rule_code', 'weekly_hours_exceeded');
        $this->assertSame(2881, $ordinary->metadata['ordinary_minutes']);
        $this->assertSame(1, $ordinary->metadata['excess_minutes']);
        $this->assertStringContainsString('48 h 01 min de jornada en días laborables', $ordinary->description);

        WorkDayCalculation::query()
            ->where('company_id', $company->id)
            ->update([
                'total_work_minutes' => 480,
                'ordinary_minutes' => 480,
                'overtime_minutes' => 0,
                'sunday_minutes' => 0,
            ]);

        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay->refresh());

        $this->assertSame(0, Alert::query()
            ->where('company_id', $company->id)
            ->whereIn('rule_code', [
                'weekly_hours_exceeded',
                'weekly_overtime_exceeded',
                'weekly_overtime_days_exceeded',
                'weekly_sunday_work',
            ])
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->count());
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

    public function test_weekly_totals_do_not_treat_extra_hours_as_excess_ordinary_hours(): void
    {
        [$company, $day, $calculation] = $this->calculatedWorkDay([
            'total_work_minutes' => 4536, 'ordinary_minutes' => 2881, 'overtime_minutes' => 1655,
            'result_snapshot' => ['ordinary_overtime' => ['daily_limit_minutes' => 480, 'weekly_limit_minutes' => 2880]],
        ]);
        $evaluate = app(EvaluateWorkDayAlertsAction::class);
        $evaluate->handle($company, $day);
        $ordinary = $day->alerts()->where('rule_code', 'weekly_hours_exceeded')->firstOrFail();
        $extra = $day->alerts()->where('rule_code', 'weekly_overtime_exceeded')->firstOrFail();
        $calculation->update(['total_work_minutes' => 4535, 'ordinary_minutes' => 2880]);
        $before = $calculation->fresh()->getAttributes();
        $evaluate->handle($company, $day->fresh());
        $this->assertSame(Alert::STATUS_CLOSED, $ordinary->fresh()->status);
        $extra->refresh();
        $this->assertSame(Alert::STATUS_NEW, $extra->status);
        $this->assertSame(2880, $extra->metadata['ordinary_minutes']);
        $this->assertSame(1115, $extra->metadata['excess_minutes']);
        $this->assertStringContainsString('27 h 35 min extra', $extra->description);
        $this->assertStringContainsString('9 h 00 min', $extra->description);
        $this->assertStringContainsString('18 h 35 min', $extra->description);
        $actor = $this->userForCompany($company, RoleKey::ADMIN_EMPRESA);
        app(ResolveAlertAction::class)->handle($company, $extra, $actor, ['status' => Alert::STATUS_CLOSED, 'resolution' => 'Seguimiento semanal registrado.']);
        // Simulate the prior metadata and wording without discarding an unchanged review.
        $extra->refresh();
        $metadata = $extra->metadata;
        unset($metadata['ordinary_minutes'], $metadata['excess_minutes']);
        $extra->update(['metadata' => $metadata, 'description' => 'La suma semanal de tiempo extraordinario supera el límite aplicable y requiere revisión.']);
        $evaluate->handle($company, $day->fresh());
        $this->assertSame(Alert::STATUS_CLOSED, $extra->fresh()->status);
        $this->assertSame($actor->id, $extra->fresh()->resolved_by);
        $this->assertSame($before, $calculation->fresh()->getAttributes());
    }

    public function test_alerts_list_is_visible_to_managers_and_scoped_by_company(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay(['overtime_minutes' => 60]);
        [$otherCompany, $otherWorkDay] = $this->calculatedWorkDay(['overtime_minutes' => 60]);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);
        app(EvaluateWorkDayAlertsAction::class)->handle($otherCompany, $otherWorkDay);
        $user = $this->userForCompany($company, RoleKey::ADMIN_EMPRESA);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('alerts.index')
            ->assertSee('Alertas')
            ->assertSee('Tiempo extra detectado');

        $this->assertSame(1, Alert::query()->where('company_id', $company->id)->count());
        $this->assertSame(1, Alert::query()->where('company_id', $otherCompany->id)->count());
    }

    public function test_absence_resolution_panel_uses_contextual_labels(): void
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
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay->refresh());
        $user = $this->userForCompany($company, RoleKey::ADMIN_EMPRESA);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('work-days.index')
            ->set('dateFrom', '2026-08-03')
            ->set('dateTo', '2026-08-03')
            ->call('openAlertsPanel', $workDay->id)
            ->assertSee('Confirmar falta')
            ->assertSee('Enviar a incidencia/ausencia')
            ->set('alertResolutionForm.status', Alert::STATUS_JUSTIFIED)
            ->assertSee('Tipo de ausencia')
            ->assertSee('Tratamiento operativo')
            ->assertSee('No procede')
            ->assertDontSee('No aprobar');
    }

    public function test_scheduled_absence_resolution_creates_attendance_incident_and_recalculates_work_day(): void
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
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay->refresh());
        $alert = Alert::query()->where('company_id', $company->id)->firstOrFail();
        $user = $this->userForCompany($company, RoleKey::ADMIN_EMPRESA);

        $incident = app(ResolveScheduledAbsenceAlertAsAttendanceIncidentAction::class)->handle($company, $alert, $user, [
            'incident_type' => AttendanceIncident::TYPE_JUSTIFIED_UNPAID_ABSENCE,
            'payment_status' => AttendanceIncident::PAYMENT_UNPAID,
            'resolution' => 'Permiso sin goce capturado desde dictamen.',
        ]);

        $this->assertSame(AttendanceIncident::TYPE_JUSTIFIED_UNPAID_ABSENCE, $incident->incident_type);
        $this->assertSame(AttendanceIncident::PAYMENT_UNPAID, $incident->payment_status);
        $this->assertSame(AttendanceIncident::STATUS_APPROVED, $incident->status);
        $this->assertSame($workDay->worker_id, $incident->worker_id);
        $this->assertSame($workDay->employment_relationship_id, $incident->employment_relationship_id);
        $this->assertSame('2026-08-03', $incident->start_date->toDateString());
        $this->assertSame('2026-08-03', $incident->end_date->toDateString());
        $this->assertSame($alert->id, $incident->metadata['source_alert_id']);

        $alert->refresh();
        $this->assertSame(Alert::STATUS_JUSTIFIED, $alert->status);
        $this->assertSame('Permiso sin goce capturado desde dictamen.', $alert->resolution);
        $this->assertSame($user->id, $alert->resolved_by);

        $workDay->refresh()->load('activeCalculation');
        $this->assertSame(WorkDay::STATUS_CALCULATED, $workDay->status);
        $this->assertSame(0, $workDay->activeCalculation->total_work_minutes);
        $this->assertSame(0, $workDay->activeCalculation->ordinary_minutes);
        $this->assertSame(AttendanceIncident::TYPE_JUSTIFIED_UNPAID_ABSENCE, data_get($workDay->metadata, 'attendance_incident.incident_type'));
        $this->assertSame(0, Alert::query()->where('company_id', $company->id)->whereIn('status', Alert::OPEN_STATUSES)->count());
    }

    public function test_changing_scheduled_absence_resolution_cancels_linked_attendance_incident(): void
    {
        [$company, $workDay] = $this->scheduledAbsenceWorkDay();
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay->refresh());
        $alert = Alert::query()->where('company_id', $company->id)->firstOrFail();
        $user = $this->userForCompany($company, RoleKey::ADMIN_EMPRESA);

        $incident = app(ResolveScheduledAbsenceAlertAsAttendanceIncidentAction::class)->handle($company, $alert, $user, [
            'incident_type' => AttendanceIncident::TYPE_JUSTIFIED_PAID_ABSENCE,
            'payment_status' => AttendanceIncident::PAYMENT_PAID,
            'resolution' => 'Falta justificada inicialmente.',
        ]);

        app(ResolveScheduledAbsenceAlertAsAttendanceIncidentAction::class)->handle($company, $alert->refresh(), $user, [
            'status' => Alert::STATUS_CLOSED,
            'resolution' => 'Se descarta la ausencia vinculada por correccion.',
        ]);

        $this->assertSame(AttendanceIncident::STATUS_CANCELLED, $incident->refresh()->status);
        $this->assertSame($user->id, $incident->cancelled_by);
        $this->assertSame('scheduled_absence_alert_resolution_changed', $incident->metadata['cancel_scope']);
        $this->assertSame(0, AttendanceIncident::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $workDay->worker_id)
            ->where('status', AttendanceIncident::STATUS_APPROVED)
            ->count());

        $alert->refresh();
        $this->assertSame(Alert::STATUS_CLOSED, $alert->status);
        $this->assertSame('Se descarta la ausencia vinculada por correccion.', $alert->resolution);
    }

    public function test_changing_scheduled_absence_justification_updates_linked_incident_without_duplicate(): void
    {
        [$company, $workDay] = $this->scheduledAbsenceWorkDay();
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay->refresh());
        $alert = Alert::query()->where('company_id', $company->id)->firstOrFail();
        $user = $this->userForCompany($company, RoleKey::ADMIN_EMPRESA);

        $incident = app(ResolveScheduledAbsenceAlertAsAttendanceIncidentAction::class)->handle($company, $alert, $user, [
            'incident_type' => AttendanceIncident::TYPE_JUSTIFIED_PAID_ABSENCE,
            'payment_status' => AttendanceIncident::PAYMENT_PAID,
            'resolution' => 'Falta justificada inicialmente.',
        ]);

        $updated = app(ResolveScheduledAbsenceAlertAsAttendanceIncidentAction::class)->handle($company, $alert->refresh(), $user, [
            'status' => Alert::STATUS_JUSTIFIED,
            'incident_type' => AttendanceIncident::TYPE_INCAPACITY,
            'payment_status' => AttendanceIncident::PAYMENT_PAID,
            'resolution' => 'Se corrige el dictamen a incapacidad.',
        ]);

        $this->assertSame($incident->id, $updated?->id);
        $this->assertSame(1, AttendanceIncident::query()->where('company_id', $company->id)->count());
        $this->assertSame(AttendanceIncident::TYPE_INCAPACITY, $incident->refresh()->incident_type);
        $this->assertSame(AttendanceIncident::STATUS_APPROVED, $incident->status);
        $this->assertSame('Se corrige el dictamen a incapacidad.', $incident->notes);
        $this->assertSame(AttendanceIncident::TYPE_JUSTIFIED_PAID_ABSENCE, $incident->metadata['change_history'][0]['previous_incident_type']);

        $alert->refresh();
        $this->assertSame(Alert::STATUS_JUSTIFIED, $alert->status);
        $this->assertSame('Se corrige el dictamen a incapacidad.', $alert->resolution);
    }

    public function test_overtime_resolution_panel_uses_contextual_labels(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay(['overtime_minutes' => 60]);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);
        $user = $this->userForCompany($company, RoleKey::ADMIN_EMPRESA);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('work-days.index')
            ->set('workspaceTab', 'incidencias')
            ->set('dateFrom', '2026-08-03')
            ->set('dateTo', '2026-08-03')
            ->call('openAlertsPanel', $workDay->id)
            ->assertSee('Autorización administrativa confirmada')
            ->assertSee('Sin autorización administrativa')
            ->assertSee('Registrar revisión y seguimiento')
            ->assertDontSee('No aprobar');
    }

    public function test_punctuality_resolution_panel_uses_specific_contextual_labels(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay([
            'late_arrival_minutes' => 12,
            'early_departure_minutes' => 8,
        ]);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);
        $user = $this->userForCompany($company, RoleKey::ADMIN_EMPRESA);
        $earlyDepartureAlert = Alert::query()
            ->where('company_id', $company->id)
            ->where('rule_code', 'early_departure_detected')
            ->firstOrFail();

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('work-days.index')
            ->set('dateFrom', '2026-08-03')
            ->set('dateTo', '2026-08-03')
            ->call('openAlertsPanel', $workDay->id)
            ->assertSee('Confirmar retardo')
            ->assertSee('Justificar retardo')
            ->set('selectedAlertId', $earlyDepartureAlert->id)
            ->assertSee('Confirmar salida anticipada')
            ->assertSee('Justificar salida anticipada');
    }

    public function test_special_legal_alerts_use_specific_contextual_resolution_labels(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay();
        $user = $this->userForCompany($company, RoleKey::ADMIN_EMPRESA);
        $alerts = collect([
            'twelve_hours_exceeded' => ['Registrar revisión y seguimiento'],
            'sunday_work' => ['Registrar revisión y seguimiento'],
            'mandatory_rest_work' => ['Registrar revisión y seguimiento'],
            'weekly_rest_missing' => ['Confirmar revisión semanal'],
        ])->mapWithKeys(function (array $labels, string $ruleCode) use ($company, $workDay): array {
            $type = AlertType::query()->create([
                'code' => $ruleCode,
                'name' => $ruleCode,
                'description' => $ruleCode,
                'default_severity' => AlertType::SEVERITY_WARNING,
                'category' => 'test',
                'status' => 'active',
            ]);

            $alert = Alert::query()->create([
                'company_id' => $company->id,
                'alert_type_id' => $type->id,
                'worker_id' => $workDay->worker_id,
                'work_day_id' => $workDay->id,
                'work_day_calculation_id' => $workDay->active_calculation_id,
                'severity' => AlertType::SEVERITY_WARNING,
                'status' => Alert::STATUS_NEW,
                'title' => $type->name,
                'description' => $type->description,
                'rule_code' => $ruleCode,
                'detected_at' => now(),
                'fingerprint' => $ruleCode.'-'.$workDay->id,
            ]);

            return [$alert->id => $labels];
        });

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $component = Volt::test('work-days.index')
            ->set('workspaceTab', 'incidencias')
            ->set('dateFrom', '2026-08-03')
            ->set('dateTo', '2026-08-03')
            ->call('openAlertsPanel', $workDay->id);

        foreach ($alerts as $alertId => $labels) {
            $component->set('selectedAlertId', $alertId);

            foreach ($labels as $label) {
                $component->assertSee($label);
            }

            $component->assertDontSee('Justificar jornada mayor a 12 horas');
        }
    }

    public function test_work_days_alert_badge_opens_resolution_panel_and_updates_status(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay(['overtime_minutes' => 60]);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);
        $user = $this->userForCompany($company, RoleKey::ADMIN_EMPRESA);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('work-days.index')
            ->set('workspaceTab', 'incidencias')
            ->set('dateFrom', '2026-08-03')
            ->set('dateTo', '2026-08-03')
            ->assertSee('Tiempo extra detectado')
            ->assertSee('Atender caso')
            ->call('openAlertsPanel', $workDay->id)
            ->assertSet('showAlertsPanel', true)
            ->assertSee('Tiempo extra detectado')
            ->set('alertResolutionForm.status', Alert::STATUS_JUSTIFIED)
            ->set('alertResolutionForm.resolution', 'Tiempo extra autorizado por operacion.')
            ->call('resolveSelectedAlert')
            ->assertHasNoErrors()
            ->assertSet('showAlertsPanel', true);

        $alert = Alert::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame(Alert::STATUS_JUSTIFIED, $alert->status);
        $this->assertSame('Tiempo extra autorizado por operacion.', $alert->resolution);
        $this->assertSame($user->id, $alert->resolved_by);
        $this->assertNotNull($alert->resolved_at);
        $this->assertSame(WorkDay::STATUS_CALCULATED, $workDay->refresh()->status);
    }

    public function test_resolved_alert_can_be_updated_with_resolution_history(): void
    {
        [$company, $workDay] = $this->calculatedWorkDay(['overtime_minutes' => 60]);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $workDay);
        $user = $this->userForCompany($company, RoleKey::ADMIN_EMPRESA);
        $alert = Alert::query()->where('company_id', $company->id)->firstOrFail();

        app(ResolveAlertAction::class)->handle($company, $alert, $user, [
            'status' => Alert::STATUS_JUSTIFIED,
            'resolution' => 'Tiempo extra autorizado por operacion.',
        ]);

        app(ResolveAlertAction::class)->handle($company, $alert->refresh(), $user, [
            'status' => Alert::STATUS_CLOSED,
            'resolution' => 'Segundo dictamen por correccion operativa.',
        ]);

        $alert->refresh();

        $this->assertSame(Alert::STATUS_CLOSED, $alert->status);
        $this->assertSame('Segundo dictamen por correccion operativa.', $alert->resolution);
        $this->assertSame(Alert::STATUS_JUSTIFIED, $alert->metadata['resolution_history'][0]['previous_status']);
        $this->assertSame('Tiempo extra autorizado por operacion.', $alert->metadata['resolution_history'][0]['previous_resolution']);
    }

    public function test_supervisor_cannot_view_alerts_list(): void
    {
        [$company] = $this->calculatedWorkDay(['overtime_minutes' => 60]);
        $user = $this->userForCompany($company, RoleKey::SUPERVISOR);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('alerts.index')
            ->assertForbidden();
    }

    public function test_overtime_review_margin_is_scoped_effective_and_preserves_all_worked_minutes(): void
    {
        [$company, $day, $calculation] = $this->calculatedWorkDay([
            'total_work_minutes' => 485, 'overtime_minutes' => 5,
            'result_snapshot' => ['ordinary_overtime' => ['daily_limit_minutes' => 480]],
        ]);
        $evaluate = app(EvaluateWorkDayAlertsAction::class);
        $evaluate->handle($company, $day);
        $extra = $day->alerts()->where('rule_code', 'overtime_detected')->firstOrFail();
        $limit = $day->alerts()->where('rule_code', 'daily_limit_exceeded')->firstOrFail();
        $foreign = Company::factory()->create();
        LegalParameter::query()->create([
            'company_id' => $foreign->id, 'code' => 'overtime_review_margin_minutes',
            'value' => ['minutes' => 10], 'effective_from' => '2026-08-01', 'status' => 'active',
        ]);
        $parameter = LegalParameter::query()->create([
            'company_id' => $company->id, 'code' => 'overtime_review_margin_minutes',
            'value' => ['minutes' => 5], 'effective_from' => '2026-08-04', 'status' => 'active',
        ]);
        $evaluate->handle($company, $day->fresh());
        $this->assertSame(Alert::STATUS_NEW, $extra->fresh()->status);
        $parameter->update(['effective_from' => '2026-08-03']);
        $before = $calculation->fresh()->getAttributes();
        $evaluate->handle($company, $day->fresh());
        $this->assertSame(Alert::STATUS_CLOSED, $extra->fresh()->status);
        $this->assertSame(Alert::STATUS_CLOSED, $limit->fresh()->status);
        $this->assertNull($extra->fresh()->resolved_by);
        $this->assertSame($before, $calculation->fresh()->getAttributes());
        $calculation->update(['total_work_minutes' => 486, 'overtime_minutes' => 6]);
        $evaluate->handle($company, $day->fresh());
        $this->assertSame(Alert::STATUS_NEW, $extra->fresh()->status);
        $this->assertSame(Alert::STATUS_NEW, $limit->fresh()->status);
        $parameter->update(['value' => ['minutes' => 0]]);
        $calculation->update(['total_work_minutes' => 485, 'overtime_minutes' => 5]);
        $evaluate->handle($company, $day->fresh());
        $this->assertSame(Alert::STATUS_NEW, $extra->fresh()->status);
    }

    public function test_margin_does_not_hide_incomplete_days_or_weekly_overtime_inside_daily_limit(): void
    {
        [$company, $day, $calculation] = $this->calculatedWorkDay([
            'total_work_minutes' => 485, 'overtime_minutes' => 5,
            'result_snapshot' => ['ordinary_overtime' => ['daily_limit_minutes' => 480], 'issues' => ['missing_clock_out']],
        ]);
        LegalParameter::query()->create([
            'company_id' => $company->id, 'code' => 'overtime_review_margin_minutes',
            'value' => ['minutes' => 10], 'effective_from' => '2026-08-01', 'status' => 'active',
        ]);
        $evaluate = app(EvaluateWorkDayAlertsAction::class);
        $evaluate->handle($company, $day);
        $this->assertTrue($day->alerts()->where('rule_code', 'incomplete_work_day')->where('status', 'new')->exists());
        $this->assertTrue($day->alerts()->where('rule_code', 'overtime_detected')->where('status', 'new')->exists());
        $calculation->update(['total_work_minutes' => 480, 'ordinary_minutes' => 475, 'result_snapshot' => [
            'issues' => [], 'ordinary_overtime' => ['daily_limit_minutes' => 480],
        ]]);
        $evaluate->handle($company, $day->fresh());
        $this->assertTrue($day->alerts()->where('rule_code', 'overtime_detected')->where('status', 'new')->exists());
    }

    public function test_overtime_authorization_keeps_duration_review_independent(): void
    {
        [$company, $day, $calculation] = $this->calculatedWorkDay([
            'total_work_minutes' => 870, 'overtime_minutes' => 390,
            'result_snapshot' => ['ordinary_overtime' => ['daily_limit_minutes' => 480]],
        ]);
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $day);
        $actor = $this->userForCompany($company, RoleKey::ADMIN_EMPRESA);
        $extra = $day->alerts()->where('rule_code', 'overtime_detected')->firstOrFail();
        $limit = $day->alerts()->where('rule_code', 'daily_limit_exceeded')->firstOrFail();
        [$otherCompany, $otherDay] = $this->calculatedWorkDay([
            'total_work_minutes' => 485, 'overtime_minutes' => 5,
            'result_snapshot' => ['ordinary_overtime' => ['daily_limit_minutes' => 480]],
        ]);
        app(EvaluateWorkDayAlertsAction::class)->handle($otherCompany, $otherDay);
        $otherLimit = $otherDay->alerts()->where('rule_code', 'daily_limit_exceeded')->firstOrFail();
        $before = $calculation->fresh()->getAttributes();
        app(ResolveAlertAction::class)->handle($company, $extra, $actor, [
            'status' => Alert::STATUS_CORRECTED, 'resolution' => 'Sin autorización registrada.',
        ]);
        $this->assertSame(Alert::STATUS_NEW, $limit->fresh()->status);
        app(ResolveAlertAction::class)->handle($company, $extra->fresh(), $actor, [
            'status' => Alert::STATUS_JUSTIFIED, 'resolution' => 'Tiempo extra autorizado por supervisión.',
        ]);
        $limit->refresh();
        $this->assertSame(Alert::STATUS_NEW, $limit->status);
        $this->assertNull($limit->resolved_by);
        $this->assertSame(Alert::STATUS_NEW, $otherLimit->fresh()->status);
        $this->assertCount(3, $limit->metadata['exceeded_limits']);
        $this->assertFalse($day->alerts()->whereIn('rule_code', ['twelve_hours_exceeded', 'daily_overtime_over_three_hours', 'long_work_day'])->exists());
        $this->assertSame($before, $calculation->fresh()->getAttributes());
        app(EvaluateWorkDayAlertsAction::class)->handle($company, $day->fresh());
        $this->assertSame(Alert::STATUS_NEW, $limit->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $calculationOverrides
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
            'break_minutes' => 30,
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

    private function scheduledAbsenceWorkDay(): array
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

        return [$company, $workDay->refresh()];
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
