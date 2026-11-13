<?php

use App\Domains\TimeRecords\Actions\ImportAttendanceClockAction;
use App\Models\AttendanceClock;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\TimeEvent;
use App\Models\Worker;
use Illuminate\Support\Facades\Queue;

it('imports the four ADMS states once and leaves rejected batches unconfirmed', function () {
    Queue::fake();
    $company = Company::factory()->create();
    $center = Center::factory()->for($company)->create();
    $worker = Worker::factory()->for($company)->create();
    EmploymentRelationship::factory()->for($company)->for($center)->for($worker)->create(['started_at' => '2026-01-01']);
    $clock = AttendanceClock::create(['company_id' => $company->id, 'center_id' => $center->id, 'name' => 'Test', 'serial' => 'TEST1']);
    $clock->workers()->attach($worker->id, ['clock_user_id' => '1']);
    $import = app(ImportAttendanceClockAction::class);

    expect(fn () => $import->handle('TEST1', "1\t2026-11-12 07:59:00\t0\n2\t2026-11-12 08:00:00\t0"))
        ->toThrow(InvalidArgumentException::class);
    expect(TimeEvent::count())->toBe(0);

    $batch = "1\t2026-11-12 08:00:00\t0\t1\n1\t2026-11-12 12:00:00\t4\t1\n1\t2026-11-12 13:00:00\t5\t1\n1\t2026-11-12 17:00:00\t1\t1";
    expect($import->handle('test1', $batch))->toBe(4)
        ->and($import->handle('TEST1', $batch))->toBe(4);
    expect(TimeEvent::where('company_id', $company->id)->where('worker_id', $worker->id)
        ->where('source', 'integration')->orderBy('occurred_at_utc')->pluck('event_type')->all())
        ->toBe(['clock_in', 'break_start', 'break_end', 'clock_out']);
});
