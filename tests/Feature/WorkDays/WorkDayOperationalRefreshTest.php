<?php

namespace Tests\Feature\WorkDays;

use App\Models\Center;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\EmploymentRelationship;
use App\Models\Role;
use App\Models\ScheduleBatch;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WorkDayOperationalRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_settings_save_work_days_auto_refresh_time(): void
    {
        [$company, $user] = $this->companyUserAndPublishedDay();

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('company-settings.index')
            ->set('settingsForm.payroll_period_type', 'weekly')
            ->set('settingsForm.default_timezone', 'America/Mexico_City')
            ->set('settingsForm.default_closure_day', 5)
            ->set('settingsForm.work_days_auto_refresh_time', '02:30')
            ->set('settingsForm.allow_worker_corrections', true)
            ->set('settingsForm.require_pin_for_kiosk', false)
            ->set('settingsForm.require_pin_for_confirmation', true)
            ->call('updateSettings')
            ->assertHasNoErrors();

        $this->assertSame('02:30', substr((string) $company->setting->refresh()->work_days_auto_refresh_time, 0, 5));
    }

    public function test_ui_manual_process_creates_work_days_for_selected_range(): void
    {
        [$company, $user] = $this->companyUserAndPublishedDay();

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('work-days.index')
            ->call('openProcessPanel')
            ->set('processForm.date_from', '2026-08-03')
            ->set('processForm.date_to', '2026-08-03')
            ->set('processForm.reason', 'Reproceso manual por prueba operativa')
            ->call('processWorkDays')
            ->assertHasNoErrors()
            ->assertSee('Recalculo de jornadas');

        $this->assertSame(1, WorkDay::query()->where('company_id', $company->id)->count());
        $summary = $company->setting->refresh()->work_days_last_refresh_summary;
        $this->assertSame('manual_ui', $summary['mode']);
        $this->assertSame($user->id, $summary['actor_id']);
        $this->assertSame('user', $summary['generated_by_type']);
        $this->assertSame('Reproceso manual por prueba operativa', $summary['reason']);
    }

    public function test_company_settings_do_not_show_manual_work_days_refresh(): void
    {
        [$company, $user] = $this->companyUserAndPublishedDay();

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        Volt::test('company-settings.index')
            ->assertSee('Hora automatica de jornadas')
            ->assertDontSee('Recalcular jornadas');
    }

    public function test_manual_command_refreshes_company_range(): void
    {
        [$company] = $this->companyUserAndPublishedDay();

        Artisan::call('work-days:refresh', [
            '--company' => $company->id,
            '--from' => '2026-08-03',
            '--to' => '2026-08-03',
            '--reason' => 'Reproceso por consola de prueba',
        ]);

        $this->assertStringContainsString('Jornadas procesadas', Artisan::output());
        $this->assertSame(1, WorkDay::query()->where('company_id', $company->id)->count());
        $summary = $company->setting->refresh()->work_days_last_refresh_summary;
        $this->assertSame('manual_command', $summary['mode']);
        $this->assertSame('user', $summary['generated_by_type']);
        $this->assertSame('Reproceso por consola de prueba', $summary['reason']);
    }

    public function test_manual_command_requires_reason(): void
    {
        [$company] = $this->companyUserAndPublishedDay();

        $exitCode = Artisan::call('work-days:refresh', [
            '--company' => $company->id,
            '--from' => '2026-08-03',
            '--to' => '2026-08-03',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('La opcion --reason es requerida para reproceso manual.', Artisan::output());
        $this->assertSame(0, WorkDay::query()->where('company_id', $company->id)->count());
    }

    public function test_auto_refresh_runs_only_when_company_local_time_is_due(): void
    {
        [$company] = $this->companyUserAndPublishedDay();
        [$otherCompany] = $this->companyUserAndPublishedDay('OTHER');
        $company->setting->forceFill([
            'default_timezone' => 'America/Mexico_City',
            'work_days_auto_refresh_time' => '02:00',
        ])->save();
        $otherCompany->setting->forceFill([
            'default_timezone' => 'America/Mexico_City',
            'work_days_auto_refresh_time' => '03:00',
        ])->save();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03 08:00:00', 'UTC'));

        try {
            Artisan::call('work-days:auto-refresh');
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertSame(1, WorkDay::query()->where('company_id', $company->id)->count());
        $this->assertSame(0, WorkDay::query()->where('company_id', $otherCompany->id)->count());
        $this->assertSame('auto', $company->setting->refresh()->work_days_last_refresh_summary['mode']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-03 08:00:00', 'UTC'));
        try {
            Artisan::call('work-days:auto-refresh');
        } finally {
            CarbonImmutable::setTestNow();
        }

        $this->assertSame(1, WorkDay::query()->where('company_id', $company->id)->count());
    }

    /**
     * @return array{0: Company, 1: User, 2: EmploymentRelationship}
     */
    private function companyUserAndPublishedDay(string $suffix = 'MAIN'): array
    {
        $role = Role::query()->firstOrCreate(
            ['key' => RoleKey::ADMIN_EMPRESA],
            ['name' => 'Administrador', 'description' => null, 'is_system' => true],
        );
        $company = Company::factory()->create(['status' => 'active', 'timezone' => 'America/Mexico_City']);
        $company->setting()->create(array_replace(Company::defaultSettings(), [
            'default_timezone' => 'America/Mexico_City',
        ]));
        $user = User::factory()->create(['email' => strtolower($suffix).'@example.test']);
        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);
        $center = Center::factory()->create(['company_id' => $company->id, 'status' => 'active', 'timezone' => 'America/Mexico_City']);
        $worker = Worker::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $relationship = EmploymentRelationship::factory()->create([
            'company_id' => $company->id,
            'worker_id' => $worker->id,
            'center_id' => $center->id,
            'started_at' => '2026-08-01',
            'ended_at' => null,
            'status' => 'active',
        ]);
        $batch = ScheduleBatch::factory()->create([
            'company_id' => $company->id,
            'center_id' => $center->id,
            'period_start' => '2026-08-03',
            'period_end' => '2026-08-09',
            'version' => 1,
            'status' => 'published',
            'snapshot_sha256' => str_repeat('b', 64),
        ]);
        $assignment = DailyScheduleAssignment::factory()->create([
            'company_id' => $company->id,
            'schedule_batch_id' => $batch->id,
            'employment_relationship_id' => $relationship->id,
            'work_date' => '2026-08-03',
            'day_type' => 'shift',
            'timezone' => 'America/Mexico_City',
        ]);
        DailyScheduleSegment::factory()->create([
            'company_id' => $company->id,
            'daily_schedule_assignment_id' => $assignment->id,
            'segment_type' => 'work',
            'duration_minutes' => 480,
        ]);

        return [$company->refresh(), $user, $relationship];
    }
}
