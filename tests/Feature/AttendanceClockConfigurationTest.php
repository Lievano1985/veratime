<?php

use App\Domains\TimeRecords\Actions\ConfigureAttendanceClockAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\Worker;
use Illuminate\Validation\ValidationException;

it('scopes clock assignments to the company and makes the local ID unique per clock', function () {
    $company = Company::factory()->create();
    $other = Company::factory()->create();
    $center = Center::factory()->create(['company_id' => $company->id]);
    $worker = Worker::factory()->create(['company_id' => $company->id, 'employee_code' => 'DEMO-DIA-001']);
    $otherCenter = Center::factory()->create(['company_id' => $other->id]);
    $otherWorker = Worker::factory()->create(['company_id' => $other->id]);
    $action = app(ConfigureAttendanceClockAction::class);
    $data = ['clockName' => 'MB10-VL', 'serial' => 'TEST-SN', 'centerId' => $center->id];

    expect(fn () => $action->register($other, $data))->toThrow(ValidationException::class);
    $clock = $action->register($company, $data);
    expect(fn () => $action->register($other, [...$data, 'serial' => 'test-sn', 'centerId' => $otherCenter->id]))
        ->toThrow(ValidationException::class);
    $second = $action->register($company, [...$data, 'serial' => 'TEST-SN-2']);

    $link = ['clockId' => $clock->id, 'clockUserId' => '1', 'employeeCode' => $worker->employee_code];
    expect(fn () => $action->link($other, $link))->toThrow(ValidationException::class);
    expect(fn () => $action->link($company, [...$link, 'employeeCode' => $otherWorker->employee_code]))
        ->toThrow(ValidationException::class);

    $action->link($company, $link);
    $action->link($company, [...$link, 'clockId' => $second->id]);
    expect(fn () => $action->link($company, $link))->toThrow(ValidationException::class);
    expect($clock->workers()->first()->id)->toBe($worker->id)
        ->and($second->workers()->first()->pivot->clock_user_id)->toBe('1');
});
