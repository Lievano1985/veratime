<?php

namespace Tests\Feature\Dashboard;

use App\Domains\Dashboard\Actions\BuildOperationalDashboardAction;
use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Domains\WorkDays\Actions\ListWorkDaysAction;
use App\Domains\WorkDays\Support\ComplianceCalculationReadiness;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\LegalRule;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use App\Support\RoleKey;
use Database\Seeders\LegalRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalDashboardComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LegalRuleSeeder::class);
    }

    public function test_pending_calculations_are_not_reported_as_compliant(): void
    {
        [$company, $user, $relationship] = $this->fixture();
        $this->day($relationship, '2026-10-09', ['classification' => WorkDayCalculation::CLASSIFICATION_PENDING, 'ordinary_minutes' => 0]);

        $summary = $this->summary($company, $user);

        $this->assertSame(1, $summary['pending_workers']);
        $this->assertSame(0, $summary['evaluated_workers']);
        $this->assertSame(0, $summary['compliant_workers']);
        $this->assertNull($summary['compliance_percentage']);
    }

    public function test_one_incomplete_day_keeps_the_whole_worker_pending(): void
    {
        [$company, $user, $relationship] = $this->fixture();
        $this->day($relationship, '2026-10-08');
        $this->day($relationship, '2026-10-09', ['result_snapshot' => ['issues' => ['missing_clock_out']]]);

        $summary = $this->summary($company, $user);
        $this->assertSame(1, $summary['pending_workers']);
        $this->assertSame(0, $summary['compliant_workers']);
    }

    public function test_a_scheduled_day_without_calculation_keeps_the_worker_pending(): void
    {
        [$company, $user, $relationship] = $this->fixture();
        $this->day($relationship, '2026-10-08');
        $day = $this->day($relationship, '2026-10-09');
        $day->update(['active_calculation_id' => null]);

        $this->assertSame(1, $this->summary($company, $user)['pending_workers']);
    }

    public function test_unprocessed_overtime_and_missing_rules_are_not_assumed_valid(): void
    {
        [$company, $user, $relationship] = $this->fixture();
        $day = $this->day($relationship, '2026-10-09', ['ordinary_minutes' => 0, 'overtime_minutes' => 0]);
        $this->assertSame(1, $this->summary($company, $user)['pending_workers']);

        $day->activeCalculation->update(['ordinary_minutes' => 480]);
        LegalRule::query()->where('code', 'weekly_overtime_double_limit')->update(['status' => 'inactive']);
        $this->assertSame(1, $this->summary($company, $user)['pending_workers']);
    }

    public function test_compliance_percentage_does_not_hide_pending_people(): void
    {
        [$company, $user, $ready] = $this->fixture();
        $pending = EmploymentRelationship::factory()->forCompany($company)->create(['started_at' => '2026-09-01']);
        $this->day($ready, '2026-10-09');
        $this->day($pending, '2026-10-09', ['classification' => WorkDayCalculation::CLASSIFICATION_PENDING]);

        $summary = $this->summary($company, $user);
        $this->assertSame(2, $summary['total_workers']);
        $this->assertSame(1, $summary['evaluated_workers']);
        $this->assertSame(1, $summary['pending_workers']);
        $this->assertSame(1, $summary['compliant_workers']);
        $this->assertSame(50, $summary['compliance_percentage']);
    }

    public function test_historical_work_is_kept_after_worker_and_relationship_are_terminated(): void
    {
        [$company, $user, $relationship] = $this->fixture();
        $this->day($relationship, '2026-10-09');
        $relationship->update(['status' => 'terminated', 'ended_at' => '2026-10-11']);
        $relationship->worker->update(['status' => 'terminated']);

        $summary = $this->summary($company, $user);
        $this->assertSame(1, $summary['evaluated_workers']);
        $this->assertSame(1, $summary['compliant_workers']);
    }

    public function test_historical_compliance_keeps_company_and_center_scope(): void
    {
        [$company, $manager, $allowed] = $this->fixture();
        $outside = EmploymentRelationship::factory()->forCompany($company)->create(['started_at' => '2026-09-01']);
        [$otherCompany, $otherUser, $foreign] = $this->fixture();
        $this->day($allowed, '2026-10-09', ['overtime_minutes' => 600, 'total_work_minutes' => 1080]);
        $this->day($outside, '2026-10-09', ['overtime_minutes' => 600, 'total_work_minutes' => 1080]);
        $this->day($foreign, '2026-10-09', ['overtime_minutes' => 600, 'total_work_minutes' => 1080]);
        $operator = User::factory()->create(['status' => 'active']);
        $role = Role::factory()->create(['key' => RoleKey::RH_OPERATIVO]);
        $operator->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active']);
        app(AssignOperationalScopeAction::class)->handle($company, $operator, ['effective_from' => '2026-09-01'], center: $allowed->center);
        $allowed->update(['status' => 'terminated', 'ended_at' => '2026-10-11']);

        $this->assertSame(1, $this->summary($company, $operator)['total_workers']);
        $this->assertSame([$allowed->worker_id], array_column($this->summary($company, $operator)['worker_details']['overtime'], 'worker_id'));
        $this->assertSame(2, $this->summary($company, $manager)['total_workers']);
    }

    public function test_next_legal_change_is_future_relative_to_the_reference_date(): void
    {
        [$company, $user] = $this->fixture();

        foreach (['2026-12-31' => '2027-01-01', '2027-01-01' => '2028-01-01', '2027-01-04' => '2028-01-01', '2030-01-01' => null] as $date => $nextDate) {
            $summary = $this->summary($company, $user, $date);
            $this->assertSame($nextDate, $summary['next_weekly_limit_effective_from']);
        }

        $summary = $this->summary($company, $user, '2027-01-04');
        $this->assertSame(2880, $summary['weekly_limit_minutes']);
        $this->assertSame(2760, $summary['current_weekly_limit_minutes']);
    }

    public function test_unit_scope_uses_assignment_dates_of_the_historical_work_day(): void
    {
        [$company, $manager, $relationship] = $this->fixture();
        $this->day($relationship, '2026-10-09');
        $this->day($relationship, '2026-10-08');
        $unit = OrganizationalUnit::factory()->forCenter($relationship->center)->create();
        EmploymentUnitAssignment::query()->forceCreate([
            'company_id' => $company->id,
            'employment_relationship_id' => $relationship->id,
            'organizational_unit_id' => $unit->id,
            'effective_from' => '2026-10-09',
            'effective_to' => '2026-10-11',
            'assignment_type' => 'primary',
            'status' => 'active',
            'source' => 'manual',
        ]);
        $relationship->update(['status' => 'terminated', 'ended_at' => '2026-10-11']);
        $outside = EmploymentRelationship::factory()->forCompany($company)->create(['center_id' => $relationship->center_id]);
        $this->day($outside, '2026-10-09');
        $supervisor = User::factory()->create(['status' => 'active']);
        $role = Role::factory()->create(['key' => RoleKey::SUPERVISOR]);
        $supervisor->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active']);
        app(AssignOperationalScopeAction::class)->handle($company, $supervisor, ['effective_from' => '2026-09-01'], unit: $unit);

        $summary = $this->summary($company, $supervisor);
        $this->assertSame(1, $summary['total_workers']);
        $this->assertSame(1, $summary['evaluated_workers']);
        $this->assertSame(1, $summary['compliant_workers']);
        $blockedCenter = Center::factory()->for($company)->create();
        $blocked = app(BuildOperationalDashboardAction::class)->handle($company, $supervisor, '2026-10-12', $blockedCenter->id);
        $this->assertSame(0, $blocked['labor_compliance']['total_workers']);
    }

    public function test_excess_details_match_counters_and_preserve_company_and_center_scope(): void
    {
        [$company, $user, $relationship] = $this->fixture();
        for ($offset = 0; $offset < 7; $offset++) {
            $extra = $offset === 6 ? 300 : 100;
            $this->day($relationship, '2026-10-'.str_pad((string) (5 + $offset), 2, '0', STR_PAD_LEFT), [
                'ordinary_minutes' => 480, 'overtime_minutes' => $extra, 'total_work_minutes' => 480 + $extra,
            ]);
        }
        [$foreignCompany, $foreignUser, $foreign] = $this->fixture();
        $this->day($foreign, '2026-10-09', ['overtime_minutes' => 700, 'total_work_minutes' => 1180]);
        $summary = $this->summary($company, $user);
        $details = $summary['worker_details'];
        foreach (['ordinary' => 'exceeded_workers', 'overtime' => 'overtime_limit_exceeded_workers', 'long_days' => 'over_twelve_hours_workers'] as $key => $counter) {
            $this->assertCount($summary[$counter], $details[$key]);
            $this->assertSame([$relationship->worker_id], array_column($details[$key], 'worker_id'));
        }
        $this->assertSame(900, $details['overtime'][0]['minutes']);
        $this->assertSame(540, $details['overtime'][0]['limit_minutes']);
        $this->assertSame(360, $details['overtime'][0]['excess_minutes']);
        $this->assertCount(7, $details['overtime'][0]['days']);
        $this->assertSame(780, $details['long_days'][0]['minutes']);
        $this->assertSame(60, $details['long_days'][0]['excess_minutes']);
        $center = Center::factory()->for($company)->create();
        $filtered = app(BuildOperationalDashboardAction::class)->handle($company, $user, '2026-10-12', $center->id);
        $this->assertSame([], $filtered['labor_compliance']['worker_details']['overtime']);

        $response = $this->actingAs($user)->get(route('dashboard', ['date' => '2026-10-12']));
        $response->assertOk()->assertSee('Ver personas (1)')->assertSee($relationship->worker->full_name)
            ->assertSee('Cerrar resumen')->assertDontSee($foreign->worker->full_name);
    }

    public function test_older_alert_details_group_people_without_changing_the_alert_counter(): void
    {
        [$company, $user, $relationship] = $this->fixture();
        $day = $this->day($relationship, '2026-10-02');
        $type = AlertType::query()->create(['code' => 'test_detail', 'name' => 'Prueba', 'default_severity' => 'warning', 'category' => 'daily', 'status' => 'active']);
        foreach (['new', 'in_review', 'closed'] as $status) {
            Alert::query()->create([
                'company_id' => $company->id, 'worker_id' => $relationship->worker_id,
                'work_day_id' => $day->id, 'alert_type_id' => $type->id,
                'status' => $status, 'severity' => 'warning', 'title' => 'Alerta '.$status,
                'fingerprint' => hash('sha256', $status), 'detected_at' => '2026-10-02 12:00:00',
            ]);
        }
        $summary = $this->summary($company, $user);
        $this->assertSame(2, $summary['older_open_alerts']);
        $this->assertCount(1, $summary['worker_details']['older_alerts']);
        $person = $summary['worker_details']['older_alerts'][0];
        $this->assertSame($relationship->worker_id, $person['worker_id']);
        $this->assertCount(2, $person['alerts']);
        $this->assertNull($person['limit_minutes']);
    }

    public function test_long_day_card_counts_work_days_separately_from_people_with_period_and_center_scope(): void
    {
        [$company, $user, $relationship] = $this->fixture();
        foreach (['2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08'] as $date) {
            $this->day($relationship, $date, ['total_work_minutes' => 870, 'ordinary_minutes' => 480, 'overtime_minutes' => 390]);
        }
        $this->day($relationship, '2026-10-09', ['total_work_minutes' => 720, 'ordinary_minutes' => 480, 'overtime_minutes' => 240]);
        $this->day($relationship, '2026-10-10');
        $this->day($relationship, '2026-10-12', ['total_work_minutes' => 870, 'ordinary_minutes' => 480, 'overtime_minutes' => 390]);
        [$foreign, $foreignUser, $foreignRelationship] = $this->fixture();
        $this->day($foreignRelationship, '2026-10-05', ['total_work_minutes' => 870]);
        $summary = $this->summary($company, $user);
        $this->assertSame(4, $summary['over_twelve_hour_days']);
        $this->assertSame(1, $summary['over_twelve_hours_workers']);
        $this->assertCount(1, $summary['worker_details']['long_days']);
        $detail = $summary['worker_details']['long_days'][0];
        $this->assertCount(4, $detail['days']);
        $this->assertSame(['05/10/2026', '06/10/2026', '07/10/2026', '08/10/2026'], array_column($detail['days'], 'date'));
        $this->assertTrue(collect($detail['days'])->every(fn (array $day): bool => $day['total_minutes'] > 720));
        $this->assertSame('Jornadas mayores a 12 horas de la semana', $detail['days_caption']);
        parse_str(parse_url($summary['worker_details']['long_days'][0]['work_days_url'], PHP_URL_QUERY), $linkFilters);
        $this->assertSame('jornadas', $linkFilters['tab']);
        $this->assertSame('2026-10-05', $linkFilters['from']);
        $this->assertSame('2026-10-11', $linkFilters['to']);
        $this->assertSame((string) $relationship->worker_id, $linkFilters['worker']);
        $this->assertSame('twelve_hours_exceeded', $linkFilters['conflicto']);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        $this->get(route('dashboard', ['date' => '2026-10-12']))->assertOk()->assertSee('1 persona involucrada en el período.')
            ->assertSee('Ver esas jornadas')
            ->assertViewHas('summary', fn ($data) => $data['labor_compliance']['over_twelve_hour_days'] === 4);
        Livewire::withQueryParams($linkFilters)->test('work-days.index')
            ->assertSet('workerIdFilter', (string) $relationship->worker_id)
            ->assertSee('Filtro del dashboard: jornadas mayores a 12 horas')
            ->assertViewHas('workDays', fn ($days) => $days->total() === 4 && $days->pluck('worker_id')->unique()->all() === [$relationship->worker_id])
            ->call('clearFilters')->assertSet('workerIdFilter', '')->assertSet('calculatedConflict', '');
        Livewire::withQueryParams(array_replace($linkFilters, ['worker' => (string) $foreignRelationship->worker_id, 'search' => '']))->test('work-days.index')
            ->assertViewHas('workDays', fn ($days) => $days->total() === 0);
        $second = EmploymentRelationship::factory()->forCompany($company)->create(['started_at' => '2026-09-01']);
        $this->day($second, '2026-10-05', ['total_work_minutes' => 800, 'ordinary_minutes' => 480, 'overtime_minutes' => 320]);
        $summary = $this->summary($company, $user);
        $this->assertSame(5, $summary['over_twelve_hour_days']);
        $this->assertSame(2, $summary['over_twelve_hours_workers']);
        $scoped = app(BuildOperationalDashboardAction::class)->handle($company, $user, '2026-10-12', $relationship->center_id)['labor_compliance'];
        $this->assertSame(4, $scoped['over_twelve_hour_days']);
        $this->assertSame(1, $scoped['over_twelve_hours_workers']);
    }

    public function test_pending_counter_opens_only_days_blocking_evaluation_in_the_selected_week(): void
    {
        [$company, $user, $relationship] = $this->fixture();
        $this->day($relationship, '2026-10-05');
        $missing = $this->day($relationship, '2026-10-06');
        $missing->update(['active_calculation_id' => null, 'expected_work_minutes' => 480]);
        $second = EmploymentRelationship::factory()->forCompany($company)->create(['started_at' => '2026-09-01']);
        $incomplete = $this->day($second, '2026-10-07', ['result_snapshot' => ['issues' => ['missing_clock_out']]]);
        $legalPending = $this->day($second, '2026-10-08', ['explanation' => ['legal_pending' => true]]);
        $this->day($second, '2026-10-09');
        $unclassified = $this->day($second, '2026-10-10', ['classification' => WorkDayCalculation::CLASSIFICATION_PENDING]);
        $unprocessed = $this->day($second, '2026-10-11', ['ordinary_minutes' => 0, 'overtime_minutes' => 0]);
        $this->day($second, '2026-10-12', ['classification' => WorkDayCalculation::CLASSIFICATION_PENDING]);
        [$foreign, $foreignUser, $foreignRelationship] = $this->fixture();
        $this->day($foreignRelationship, '2026-10-06', ['classification' => WorkDayCalculation::CLASSIFICATION_PENDING]);
        $summary = $this->summary($company, $user);
        $this->assertSame(2, $summary['pending_workers']);
        parse_str(parse_url($summary['pending_work_days_url'], PHP_URL_QUERY), $filters);
        $this->assertSame('2026-10-05', $filters['from']);
        $this->assertSame('2026-10-11', $filters['to']);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);
        $this->get(route('dashboard', ['date' => '2026-10-12']))->assertOk()->assertSee('Ver jornadas pendientes de evaluación');
        Livewire::withQueryParams($filters)->test('work-days.index')->assertSee('Quitar filtro de evaluación')
            ->assertViewHas('workDays', function ($days) use ($missing, $incomplete, $legalPending, $unclassified, $unprocessed) {
                $this->assertEqualsCanonicalizing([$missing->id, $incomplete->id, $legalPending->id, $unclassified->id, $unprocessed->id], $days->pluck('id')->all());

                return $days->total() === 5;
            });
        LegalRule::where('code', 'maximum_weekly_hours')->update(['status' => 'inactive']);
        Livewire::withQueryParams($filters)->test('work-days.index')
            ->assertViewHas('workDays', fn ($days) => $days->total() === 7);
    }

    public function test_attendance_decisions_block_evaluation_until_resolved_but_excess_reviews_do_not(): void
    {
        [$company, $user, $relationship] = $this->fixture();
        $day = $this->day($relationship, '2026-10-09');
        $type = AlertType::query()->create(['code' => 'attendance_readiness', 'name' => 'Asistencia', 'default_severity' => 'warning', 'category' => 'daily', 'status' => 'active']);
        $alert = Alert::query()->create([
            'company_id' => $company->id, 'worker_id' => $relationship->worker_id,
            'work_day_id' => $day->id, 'alert_type_id' => $type->id,
            'status' => Alert::STATUS_NEW, 'severity' => 'warning', 'title' => 'Pendiente',
            'rule_code' => 'late_arrival_detected', 'fingerprint' => hash('sha256', 'attendance-readiness'), 'detected_at' => now(),
        ]);
        foreach (['late_arrival_detected', 'early_departure_detected', 'scheduled_absence', 'incomplete_work_day'] as $rule) {
            foreach (Alert::OPEN_STATUSES as $status) {
                $alert->update(['rule_code' => $rule, 'status' => $status]);
                $this->assertSame(1, $this->summary($company, $user)['pending_workers']);
                $this->assertSame([$day->id], $this->pendingDayIds($company));
            }
        }
        foreach ([Alert::STATUS_CORRECTED, Alert::STATUS_JUSTIFIED, Alert::STATUS_CLOSED] as $status) {
            $alert->update(['status' => $status]);
            $this->assertSame(0, $this->summary($company, $user)['pending_workers']);
            $this->assertSame([], $this->pendingDayIds($company));
        }
        foreach (['overtime_detected', 'scheduled_rest_work', 'twelve_hours_exceeded'] as $rule) {
            $alert->update(['rule_code' => $rule, 'status' => Alert::STATUS_NEW]);
            $this->assertSame(0, $this->summary($company, $user)['pending_workers']);
            $this->assertSame([], $this->pendingDayIds($company));
        }
    }

    public function test_approved_absences_are_ready_without_worked_hours_but_unresolved_or_inconsistent_days_are_not(): void
    {
        [$company, $user, $relationship] = $this->fixture();
        $day = $this->day($relationship, '2026-10-09', [
            'classification' => WorkDayCalculation::CLASSIFICATION_PENDING,
            'total_work_minutes' => 0, 'ordinary_minutes' => 0, 'overtime_minutes' => 0,
            'explanation' => ['legal_pending' => true],
        ]);
        foreach (['vacation', 'paid_permission', 'incapacity', 'unjustified_absence'] as $type) {
            $day->activeCalculation->update(['result_snapshot' => ['issues' => [], 'attendance_incident' => ['incident_type' => $type, 'status' => 'approved']]]);
            $this->assertTrue(ComplianceCalculationReadiness::isReady($day->fresh()));
            $this->assertSame(0, $this->summary($company, $user)['pending_workers']);
            $this->assertSame(1, $this->summary($company, $user)['compliant_workers']);
            $this->assertSame([], $this->pendingDayIds($company));
        }
        $day->activeCalculation->update(['result_snapshot' => ['issues' => [], 'attendance_incident' => ['status' => 'pending']]]);
        $this->assertSame(1, $this->summary($company, $user)['pending_workers']);
        $this->assertSame([$day->id], $this->pendingDayIds($company));
        $day->activeCalculation->update(['result_snapshot' => ['issues' => [], 'attendance_incident' => ['status' => 'approved']]]);
        $day->update(['valid_time_event_count' => 1]);
        $this->assertFalse(ComplianceCalculationReadiness::isReady($day->fresh()));
        $this->assertSame([$day->id], $this->pendingDayIds($company));
        $day->update(['valid_time_event_count' => 0, 'status' => WorkDay::STATUS_UNDER_REVIEW]);
        $this->assertSame(1, $this->summary($company, $user)['pending_workers']);
        $this->assertSame([$day->id], $this->pendingDayIds($company));
    }

    public function test_detected_excesses_remain_counted_while_attendance_is_pending(): void
    {
        [$company, $user, $relationship] = $this->fixture();
        $day = $this->day($relationship, '2026-10-09', [
            'total_work_minutes' => 1200, 'ordinary_minutes' => 480, 'overtime_minutes' => 720,
        ]);
        $type = AlertType::query()->create(['code' => 'pending_excess', 'name' => 'Retardo', 'default_severity' => 'warning', 'category' => 'daily', 'status' => 'active']);
        $alert = Alert::query()->create([
            'company_id' => $company->id, 'worker_id' => $relationship->worker_id,
            'work_day_id' => $day->id, 'alert_type_id' => $type->id,
            'status' => Alert::STATUS_NEW, 'severity' => 'warning', 'title' => 'Retardo',
            'rule_code' => 'late_arrival_detected', 'fingerprint' => hash('sha256', 'pending-excess'), 'detected_at' => now(),
        ]);
        $summary = $this->summary($company, $user);
        $this->assertSame(1, $summary['pending_workers']);
        $this->assertSame(0, $summary['compliant_workers']);
        $this->assertSame(1, $summary['review_workers']);
        $this->assertSame(1, $summary['pending_workers_with_excess']);
        $this->assertSame(1, $summary['overtime_limit_exceeded_workers']);
        $this->assertSame(1, $summary['over_twelve_hours_workers']);
        $this->actingAs($user)->withSession(['current_company_id' => $company->id])
            ->get(route('dashboard', ['date' => '2026-10-12']))->assertOk()
            ->assertSee('en ambos contadores');
        $alert->update(['status' => Alert::STATUS_CORRECTED]);
        $summary = $this->summary($company, $user);
        $this->assertSame(0, $summary['pending_workers']);
        $this->assertSame(1, $summary['review_workers']);
        $this->assertSame(0, $summary['pending_workers_with_excess']);
    }

    private function pendingDayIds(Company $company): array
    {
        return app(ListWorkDaysAction::class)->query($company, [
            'date_from' => '2026-10-05', 'date_to' => '2026-10-11', 'calculated_conflict' => 'pending_evaluation',
        ])->pluck('work_days.id')->all();
    }

    private function fixture(): array
    {
        $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
        $role = Role::query()->firstOrCreate(['key' => RoleKey::ADMIN_EMPRESA], ['name' => 'Administrador']);
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $relationship = EmploymentRelationship::factory()->forCompany($company)->create(['started_at' => '2026-09-01']);

        return [$company, $user, $relationship];
    }

    private function day(EmploymentRelationship $relationship, string $date, array $attributes = []): WorkDay
    {
        $day = WorkDay::factory()->create([
            'company_id' => $relationship->company_id,
            'worker_id' => $relationship->worker_id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $relationship->center_id,
            'work_date' => $date,
            'status' => WorkDay::STATUS_CALCULATED,
        ]);
        $calculation = WorkDayCalculation::factory()->create(array_replace([
            'company_id' => $relationship->company_id,
            'work_day_id' => $day->id,
            'classification' => WorkDayCalculation::CLASSIFICATION_DIURNAL,
            'total_work_minutes' => 480,
            'ordinary_minutes' => 480,
            'overtime_minutes' => 0,
            'result_snapshot' => ['issues' => []],
        ], $attributes));
        $day->update(['active_calculation_id' => $calculation->id]);

        return $day;
    }

    private function summary(Company $company, User $user, string $date = '2026-10-12'): array
    {
        return app(BuildOperationalDashboardAction::class)->handle($company, $user, $date)['labor_compliance'];
    }
}
