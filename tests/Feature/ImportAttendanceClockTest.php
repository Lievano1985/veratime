<?php

use App\Domains\TimeRecords\Actions\ImportAttendanceClockAction;
use App\Domains\TimeRecords\Actions\ResolveValidTimeEventsForWorkDateAction;
use App\Domains\TimeRecords\Exceptions\AttendanceClockImportException;
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
    $relationship = EmploymentRelationship::factory()->for($company)->for($center)->for($worker)->create(['started_at' => '2026-01-01']);
    $clock = AttendanceClock::create(['company_id' => $company->id, 'center_id' => $center->id, 'name' => 'Test', 'serial' => 'TEST1']);
    $clock->workers()->attach($worker->id, ['clock_user_id' => '1']);
    $import = app(ImportAttendanceClockAction::class);

    expect(fn () => $import->handle('TEST1', "2\t2026-11-12 08:00:00\t0\n1\t2026-11-12 08:00:00\tinvalid"))
        ->toThrow(function (AttendanceClockImportException $exception) {
            expect($exception->getMessage())->toBe('estado_no_admitido')
                ->and($exception->row)->toBe(2)
                ->and($exception->state)->toBeNull();
        });
    expect(TimeEvent::count())->toBe(0);
    $this->assertDatabaseCount('attendance_clock_pending_events', 0);

    $batch = "1\t2026-11-12 08:00:00\t0\t1\n1\t2026-11-12 12:00:00\t4\t1\n1\t2026-11-12 13:00:00\t5\t1\n1\t2026-11-12 17:00:00\t1\t1";
    $batch = "1\t2026-11-12 08:00:00\t255\n2\t2026-11-12 08:00:00\t0\n".$batch;
    expect($import->handle('test1', $batch))->toBe(6)
        ->and($import->handle('TEST1', $batch))->toBe(6);
    $this->assertDatabaseCount('attendance_clock_pending_events', 2);
    $this->assertDatabaseHas('attendance_clock_pending_events', [
        'attendance_clock_id' => $clock->id,
        'clock_user_id' => '1',
        'event_type' => 'adms:255',
        'reason' => 'estado_no_admitido',
    ]);
    $this->assertDatabaseHas('attendance_clock_pending_events', [
        'attendance_clock_id' => $clock->id,
        'clock_user_id' => '2',
        'reason' => 'sin_vinculo_con_trabajador_activo',
    ]);
    expect(TimeEvent::where('company_id', $company->id)->where('worker_id', $worker->id)
        ->where('source', 'integration')->orderBy('occurred_at_utc')->pluck('event_type')->all())
        ->toBe(['clock_in', 'break_start', 'break_end', 'clock_out']);
    $repeat = "1\t2026-11-12 11:00:30\t0\n1\t2026-11-12 17:05:20\t0";
    expect($import->handle('TEST1', $repeat))->toBe(2)
        ->and($import->handle('TEST1', $repeat))->toBe(2);
    $this->assertDatabaseHas('attendance_clock_pending_events', [
        'attendance_clock_id' => $clock->id,
        'occurred_local_time' => '11:00:30',
        'reason' => 'entrada_ya_registrada',
    ]);
    $this->assertDatabaseHas('attendance_clock_pending_events', ['reason' => 'jornada_ya_cerrada']);
    $this->assertDatabaseCount('attendance_clock_pending_events', 4);
    expect(TimeEvent::count())->toBe(4);

    $overnight = "1\t2026-11-14 06:00:00\t1\n1\t2026-11-14 02:30:00\t5\n1\t2026-11-13 22:00:00\t0\n1\t2026-11-14 02:00:00\t4";
    expect($import->handle('TEST1', $overnight))->toBe(4);
    expect(app(ResolveValidTimeEventsForWorkDateAction::class)->handle($company, $relationship, '2026-11-13')->pluck('event_type')->all())
        ->toBe(['clock_in', 'break_start', 'break_end', 'clock_out']);
    $import->handle('TEST1', "1\t2026-11-14 06:05:00\t1\n1\t2026-11-14 22:00:00\t0");
    expect(TimeEvent::count())->toBe(9);
});
