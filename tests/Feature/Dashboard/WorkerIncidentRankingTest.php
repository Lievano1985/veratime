<?php

namespace Tests\Feature\Dashboard;

use App\Domains\Dashboard\Actions\BuildWorkerIncidentRankingAction;
use App\Models\Alert;
use App\Models\AlertType;
use App\Models\Company;
use App\Models\WorkDay;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkerIncidentRankingTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_counts_inclusive_thirty_days_reviewed_alerts_and_types_without_recalculation_history(): void
    {
        $company = Company::factory()->create();
        $worker = Worker::factory()->create(['company_id' => $company->id]);
        $second = Worker::factory()->create(['company_id' => $company->id]);
        $foreign = Worker::factory()->create();
        $create = function ($person, $date, $rule, $status = 'new', $resolution = null) {
            $day = WorkDay::factory()->create(['company_id' => $person->company_id, 'worker_id' => $person->id, 'work_date' => $date]);
            $type = AlertType::firstOrCreate(['code' => $rule], ['name' => $rule, 'category' => 'daily', 'default_severity' => 'warning', 'status' => 'active']);

            return Alert::create(['company_id' => $person->company_id, 'worker_id' => $person->id, 'work_day_id' => $day->id,
                'alert_type_id' => $type->id, 'rule_code' => $rule, 'title' => $rule, 'severity' => 'warning',
                'status' => $status, 'resolution' => $resolution, 'fingerprint' => (string) Str::uuid(), 'detected_at' => now()]);
        };
        $create($worker, '2026-09-26', 'late_arrival_detected');
        $create($worker, '2026-10-25', 'overtime_detected', 'justified', 'Autorizado por RH');
        $create($second, '2026-10-24', 'late_arrival_detected');
        $create($worker, '2026-09-25', 'late_arrival_detected');
        $create($worker, '2026-10-26', 'late_arrival_detected');
        $create($foreign, '2026-10-25', 'late_arrival_detected');
        $create($worker, '2026-10-20', 'minimum_break_missing', 'closed', 'Cerrada automaticamente por recalculo.');
        $create($worker, '2026-10-19', 'long_work_day');
        $result = app(BuildWorkerIncidentRankingAction::class)->handle(Alert::where('company_id', $company->id), CarbonImmutable::parse('2026-10-25'));
        $this->assertSame('2026-09-26', $result['start']);
        $this->assertSame(3, $result['total']);
        $this->assertCount(2, $result['workers']);
        $this->assertSame($worker->id, $result['workers'][0]['id']);
        $this->assertSame(2, $result['workers'][0]['total']);
        $this->assertSame(1, $result['workers'][0]['pending']);
        $this->assertSame(1, $result['workers'][0]['reviewed']);
        $this->assertEqualsCanonicalizing(['Retardo', 'Tiempo extra detectado'], array_column($result['workers'][0]['types'], 'label'));
        $this->assertSame(2, array_sum(array_column($result['workers'][0]['types'], 'count')));
        $scoped = app(BuildWorkerIncidentRankingAction::class)->handle(Alert::where('company_id', $company->id)->where('worker_id', $second->id), CarbonImmutable::parse('2026-10-25'));
        $this->assertSame(1, $scoped['total']);
        $empty = app(BuildWorkerIncidentRankingAction::class)->handle(Alert::where('company_id', $company->id), CarbonImmutable::parse('2025-01-01'));
        $this->assertSame([], $empty['workers']);
    }
}
