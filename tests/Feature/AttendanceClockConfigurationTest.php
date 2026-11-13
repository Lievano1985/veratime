<?php

use App\Domains\TimeRecords\Actions\ConfigureAttendanceClockAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Center;
use App\Models\Company;
use App\Models\Worker;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;

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

    $action->link($company, [...$link, 'clockUserId' => '3']);
    expect(fn () => $action->unlink($other, $clock->id, $worker->id, '1'))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect($action->unlink($company, $clock->id, $otherWorker->id, '1'))->toBeFalse()
        ->and($action->unlink($company, $clock->id, $worker->id, '1'))->toBeTrue()
        ->and($clock->workers()->sole()->pivot->clock_user_id)->toBe('3')
        ->and($second->workers()->sole()->pivot->clock_user_id)->toBe('1');
});

it('renders a usable unlink action and removes only the chosen ID through the clock screen', function () {
    $company = Company::factory()->create();
    $center = Center::factory()->for($company)->create();
    $worker = Worker::factory()->for($company)->create();
    $this->mock(CurrentCompany::class)->shouldReceive('get')->andReturn($company);
    \Illuminate\Support\Facades\Gate::shouldReceive('authorize')->with('update', $company);

    $clock = app(ConfigureAttendanceClockAction::class)->register($company, [
        'clockName' => 'MB10-VL', 'serial' => 'TEST-UI', 'centerId' => $center->id,
    ]);
    $clock->workers()->attach($worker->id, ['clock_user_id' => '001']);
    $clock->workers()->attach($worker->id, ['clock_user_id' => '3']);

    $component = Volt::test('company-settings.attendance-clocks');
    $document = new DOMDocument;
    $document->loadHTML($component->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $button = $xpath->query('//li[@*[name()="wire:key"]="clock-link-'.$clock->id.'-001"]/button')->item(0);
    expect($button?->getAttribute('wire:click'))->toBe("unlinkWorker({$clock->id}, {$worker->id}, \"001\")")
        ->and($button?->getAttribute('type'))->toBe('button');

    $component->call('unlinkWorker', $clock->id, $worker->id, '001')
        ->assertHasNoErrors()
        ->assertSee('Vínculo eliminado.');
    expect($clock->workers()->sole()->pivot->clock_user_id)->toBe('3');
});
