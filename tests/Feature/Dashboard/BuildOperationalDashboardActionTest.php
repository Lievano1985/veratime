<?php

namespace Tests\Feature\Dashboard;

use App\Domains\Dashboard\Actions\BuildOperationalDashboardAction;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildOperationalDashboardActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_only_the_active_company_data(): void
    {
        $date = '2026-10-08';
        $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
        $otherCompany = Company::factory()->create(['timezone' => 'America/Mexico_City']);
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);

        [$worker, $relationship, $center] = $this->activeRelationship($company, $date);
        $this->validEvent($company, $worker, $relationship, $center, 'clock_in', 'web', $date, '08:00:00');
        $this->validEvent($company, $worker, $relationship, $center, 'manual_entry', 'admin_manual', $date, '08:05:00');

        [$otherWorker, $otherRelationship, $otherCenter] = $this->activeRelationship($otherCompany, $date);
        $this->validEvent($otherCompany, $otherWorker, $otherRelationship, $otherCenter, 'clock_in', 'web', $date, '08:00:00');

        $summary = app(BuildOperationalDashboardAction::class)->handle($company, $user, $date);

        $this->assertSame(1, $summary['metrics']['active_workers']);
        $this->assertSame(1, $summary['metrics']['working_now']);
        $this->assertSame(1, $summary['segments']['evidence']);
    }

    public function test_dashboard_builds_weekly_incident_compliance_and_annual_type_trends(): void
    {
        $date = '2026-10-08';
        $company = Company::factory()->create(['timezone' => 'America/Mexico_City']);
        $otherCompany = Company::factory()->create(['timezone' => 'America/Mexico_City']);
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company, ['role_id' => $role->id, 'status' => 'active', 'is_default' => true]);
        $type = AlertType::query()->create([
            'code' => 'late_arrival_detected',
            'name' => 'Retardo',
            'description' => 'Retardo calculado.',
            'default_severity' => AlertType::SEVERITY_WARNING,
            'category' => 'daily',
            'status' => AlertType::STATUS_ACTIVE,
        ]);

        $this->incident($company, $type, '2026-09-14 12:00:00', Alert::STATUS_CLOSED, 'retardo-cerrado');
        $this->incident($company, $type, '2026-09-15 12:00:00', Alert::STATUS_NEW, 'retardo-abierto');
        $this->incident($otherCompany, $type, '2026-09-14 12:00:00', Alert::STATUS_CLOSED, 'retardo-ajeno');

        $summary = app(BuildOperationalDashboardAction::class)->handle($company, $user, $date);
        $week = collect($summary['weekly_incident_compliance']['weeks'])->firstWhere('key', '2026-09-14');

        $this->assertSame(['total' => 2, 'closed' => 1, 'percentage' => 50], [
            'total' => $week['total'],
            'closed' => $week['closed'],
            'percentage' => $week['percentage'],
        ]);
        $this->assertSame(2, $summary['weekly_incident_compliance']['total']);
        $this->assertSame(1, $summary['weekly_incident_compliance']['closed']);
        $this->assertSame('Retardo', $summary['incidence_trends']['series'][0]['label']);
        $this->assertSame(2, $summary['incidence_trends']['series'][0]['total']);

        CarbonImmutable::setTestNow('2026-10-08 12:00:00', 'America/Mexico_City');

        try {
            $this->assertSame(2026, CarbonImmutable::now('America/Mexico_City')->year);
            $historicalSummary = app(BuildOperationalDashboardAction::class)->handle($company, $user, '2025-10-08');

            $this->assertSame(2026, $historicalSummary['incidence_trends']['year']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /** @return array{0: Worker, 1: EmploymentRelationship, 2: Center} */
    private function activeRelationship(Company $company, string $date): array
    {
        $center = Center::factory()->create(['company_id' => $company->id]);
        $worker = Worker::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $relationship = EmploymentRelationship::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $center->id,
            'started_at' => CarbonImmutable::parse($date)->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        return [$worker, $relationship, $center];
    }

    private function validEvent(Company $company, Worker $worker, EmploymentRelationship $relationship, Center $center, string $eventType, string $source, string $date, string $time): void
    {
        $local = CarbonImmutable::parse("{$date} {$time}", 'America/Mexico_City');

        TimeEvent::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'employment_relationship_id' => $relationship->id,
            'center_id' => $center->id,
            'event_type' => $eventType,
            'occurred_at_utc' => $local->utc(),
            'occurred_local_date' => $date,
            'occurred_local_time' => $time,
            'timezone' => 'America/Mexico_City',
            'received_at' => $local->utc(),
            'source' => $source,
            'status' => 'valid',
            'metadata' => [],
        ]);
    }

    private function incident(Company $company, AlertType $type, string $detectedAt, string $status, string $fingerprint): void
    {
        Alert::query()->create([
            'company_id' => $company->id,
            'alert_type_id' => $type->id,
            'severity' => AlertType::SEVERITY_WARNING,
            'status' => $status,
            'title' => $type->name,
            'description' => $type->description,
            'rule_code' => $type->code,
            'detected_at' => $detectedAt,
            'fingerprint' => hash('sha256', $fingerprint),
            'metadata' => [],
        ]);
    }
}
