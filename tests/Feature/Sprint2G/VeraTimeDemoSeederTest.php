<?php

use App\Models\Company;
use App\Models\MandatoryRestDay;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerCredential;
use Database\Seeders\VeraTimeDemoSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

it('runs the Vera Time demo seeder and creates expected Sprint 2 data', function (): void {
    $this->seed(VeraTimeDemoSeeder::class);
    $this->seed(VeraTimeDemoSeeder::class);

    $company = Company::query()->where('tax_id', 'VTD260712XX1')->firstOrFail();

    expect($company->name)->toBe('Vera Time Demo Completo')
        ->and(User::query()->whereIn('email', [
            'owner.demo@veratime.local',
            'admin.demo@veratime.local',
            'rh.demo@veratime.local',
        ])->count())->toBe(3)
        ->and($company->centers()->count())->toBe(2)
        ->and($company->workers()->count())->toBe(4)
        ->and($company->employmentRelationships()->count())->toBe(4)
        ->and($company->laborConditions()->count())->toBe(4)
        ->and($company->workerCredentials()->count())->toBe(4)
        ->and($company->schedules()->count())->toBe(2)
        ->and($company->scheduleDays()->count())->toBe(14)
        ->and($company->scheduleBreaks()->count())->toBe(2)
        ->and($company->scheduleAssignments()->count())->toBe(4)
        ->and($company->scheduleProfiles()->count())->toBe(2)
        ->and($company->scheduleProfileWeeklyRules()->count())->toBe(7)
        ->and($company->scheduleProfileAssignments()->count())->toBe(4)
        ->and($company->mandatoryRestDays()->count())->toBe(1)
        ->and(MandatoryRestDay::query()->where('type', 'electoral')->where('scope', 'subnational')->where('country_code', 'MX')->where('jurisdiction_code', 'MX-NLE')->count())->toBe(1)
        ->and(MandatoryRestDay::query()->where('capture_source', 'seeder')->count())->toBe(2)
        ->and(MandatoryRestDay::query()->where('source_reference', 'Referencia demo interna')->count())->toBe(1)
        ->and($company->timeEvents()->count())->toBe(10)
        ->and($company->timeEvents()->where('source', 'web')->count())->toBe(4)
        ->and($company->timeEvents()->where('source', 'kiosk')->count())->toBe(4)
        ->and($company->timeEvents()->where('source', 'admin_manual')->count())->toBe(2);

    $credential = WorkerCredential::query()
        ->where('company_id', $company->id)
        ->where('access_code', 'K-VT-001')
        ->firstOrFail();

    expect($credential->pin_hash)->not->toBe('1234')
        ->and(Hash::check('1234', $credential->pin_hash))->toBeTrue();

    $manualEvent = TimeEvent::query()
        ->where('company_id', $company->id)
        ->where('source', 'admin_manual')
        ->firstOrFail();

    expect($manualEvent->source_user_id)->not->toBeNull()
        ->and($manualEvent->metadata)->toHaveKey('reason')
        ->and($manualEvent->status)->toBe('pending_review')
        ->and($manualEvent->occurred_local_time)->toMatch('/^\d{2}:\d{2}:\d{2}$/');

    $worker = Worker::query()->where('company_id', $company->id)->where('employee_code', 'VT-001')->firstOrFail();
    expect($worker->company_id)->toBe($company->id);
});

it('does not create future Sprint 3 or calculation modules', function (): void {
    $this->seed(VeraTimeDemoSeeder::class);

    expect(Schema::hasTable('work_days'))->toBeTrue()
        ->and(Schema::hasTable('work_day_calculations'))->toBeTrue()
        ->and(Schema::hasTable('alerts'))->toBeFalse()
        ->and(Schema::hasTable('incidents'))->toBeFalse()
        ->and(Schema::hasTable('reports'))->toBeFalse();
});
