<?php

use App\Domains\Companies\Actions\DeleteCompanyAction;
use App\Domains\Schedules\Actions\DeleteScheduleIfUnusedAction;
use App\Models\EmploymentRelationship;
use App\Models\Schedule;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('preserves archived condition data through rollback and retirement without changing the worker', function (): void {
    $relationship = EmploymentRelationship::factory()->create();
    $workerBefore = $relationship->worker->getRawOriginal();
    $id = DB::table('retired_labor_conditions')->insertGetId([
        'company_id' => $relationship->company_id,
        'employment_relationship_id' => $relationship->id,
        'work_modality' => 'onsite',
        'weekly_hours' => 40,
        'effective_from' => '2026-08-01',
        'status' => 'active',
        'metadata' => json_encode(['recovery' => 'unchanged']),
    ]);
    $before = (array) DB::table('retired_labor_conditions')->find($id);
    $migration = require database_path('migrations/2026_10_26_000000_retire_labor_conditions_table.php');
    try {
        $migration->down();
        expect(Schema::hasTable('retired_labor_conditions'))->toBeFalse()
            ->and((array) DB::table('labor_conditions')->find($id))->toBe($before);
    } finally {
        $migration->up();
    }
    expect(Schema::hasTable('labor_conditions'))->toBeFalse()
        ->and((array) DB::table('retired_labor_conditions')->find($id))->toBe($before)
        ->and($relationship->worker->fresh()->getRawOriginal())->toBe($workerBefore);
});

it('does not overwrite an existing recovery archive', function (): void {
    Schema::create('labor_conditions', function ($table): void {
        $table->id();
    });
    $migration = require database_path('migrations/2026_10_26_000000_retire_labor_conditions_table.php');
    try {
        expect(fn () => $migration->up())->toThrow(RuntimeException::class);
        expect(Schema::hasTable('labor_conditions'))->toBeTrue()
            ->and(Schema::hasTable('retired_labor_conditions'))->toBeTrue();
    } finally {
        Schema::drop('labor_conditions');
    }
});

it('removes only the deleted company recovery archive', function (): void {
    $relationship = EmploymentRelationship::factory()->create();
    $other = EmploymentRelationship::factory()->create();
    foreach ([$relationship, $other] as $item) {
        DB::table('retired_labor_conditions')->insert([
            'company_id' => $item->company_id,
            'employment_relationship_id' => $item->id,
            'work_modality' => 'onsite',
            'effective_from' => '2026-08-01',
            'status' => 'active',
        ]);
    }
    $actor = User::factory()->create(['global_role' => RoleKey::SUPER_ADMIN, 'status' => 'active']);
    app(DeleteCompanyAction::class)->handle($actor, $relationship->company);
    $this->assertDatabaseMissing('retired_labor_conditions', ['company_id' => $relationship->company_id]);
    $this->assertDatabaseHas('retired_labor_conditions', ['company_id' => $other->company_id]);
    $this->assertModelExists($other->worker);
});

it('uses actual schedule assignments instead of archived conditions to protect schedules', function (): void {
    $relationship = EmploymentRelationship::factory()->create();
    $schedule = Schedule::factory()->create(['company_id' => $relationship->company_id]);
    DB::table('retired_labor_conditions')->insert([
        'company_id' => $relationship->company_id,
        'employment_relationship_id' => $relationship->id,
        'schedule_id' => $schedule->id,
        'work_modality' => 'onsite',
        'effective_from' => '2026-08-01',
        'status' => 'active',
    ]);
    app(DeleteScheduleIfUnusedAction::class)->handle($relationship->company, $schedule);
    $this->assertModelMissing($schedule);
    $this->assertModelExists($relationship->worker);
    $this->assertDatabaseHas('retired_labor_conditions', ['schedule_id' => $schedule->id]);
});
