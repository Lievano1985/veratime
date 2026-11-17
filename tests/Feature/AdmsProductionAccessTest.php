<?php

use App\Models\AttendanceClock;
use App\Models\Center;
use App\Models\Company;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

function registeredAdmsClock(string $serial): AttendanceClock
{
    $company = Company::factory()->create(['status' => 'active']);
    $center = Center::factory()->for($company)->create(['status' => 'active']);

    return AttendanceClock::create(['company_id' => $company->id, 'center_id' => $center->id, 'name' => 'Test', 'serial' => $serial]);
}

it('accepts registered serials from changing IPs across companies but requires HTTPS and enabled ADMS', function () {
    registeredAdmsClock('TEST1');
    registeredAdmsClock('TEST2');
    $this->app->instance('env', 'production');
    config(['adms.enabled' => false]);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10', 'HTTPS' => 'on']);
    $this->get('https://localhost/iclock/getrequest?SN=TEST1')->assertNotFound();
    config(['adms.enabled' => true]);
    foreach (['TEST1', 'TEST2', 'test1'] as $serial) {
        $this->get('https://localhost/iclock/getrequest?SN='.$serial)->assertOk();
    }
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20', 'HTTPS' => 'on'])
        ->get('https://localhost/iclock/getrequest?SN=TEST1')->assertOk();
    foreach (['WRONG', '', 'TEST%2F1'] as $serial) {
        $this->get('https://localhost/iclock/getrequest?SN='.$serial)->assertNotFound();
        $this->get('https://localhost/iclock/cdata?SN='.$serial)->assertNotFound();
    }
    $this->get('https://localhost/iclock/cdata?SN=test2')->assertOk()->assertSeeText('GET OPTION FROM: test2');
    $this->withServerVariables(['HTTPS' => 'off'])
        ->get('http://localhost/iclock/getrequest?SN=TEST1')->assertNotFound();
});

it('rejects inactive companies, inactive centers and a center belonging to another company', function () {
    $clock = registeredAdmsClock('TEST1');
    $this->app->instance('env', 'production');
    config(['adms.enabled' => true]);
    $this->withServerVariables(['HTTPS' => 'on']);
    Company::whereKey($clock->company_id)->update(['status' => 'inactive']);
    $this->get('https://localhost/iclock/getrequest?SN=TEST1')->assertNotFound();
    Company::whereKey($clock->company_id)->update(['status' => 'active']);
    Center::whereKey($clock->center_id)->update(['status' => 'inactive']);
    $this->get('https://localhost/iclock/getrequest?SN=TEST1')->assertNotFound();
    $other = registeredAdmsClock('TEST2');
    $clock->update(['center_id' => $other->center_id]);
    $this->get('https://localhost/iclock/getrequest?SN=TEST1')->assertNotFound();
});

it('logs an authorized rejected batch without writing the payload', function () {
    registeredAdmsClock('TEST1');
    $this->app->instance('env', 'production');
    config(['adms.enabled' => true]);
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')->once()->with('adms.importacion_rechazada', [
        'motivo' => 'formato_de_fila_invalido', 'fila' => 1, 'id_en_reloj' => null, 'estado' => null,
    ]);
    Log::shouldReceive('channel')->once()->with('adms')->andReturn($logger);
    $this->call('POST', 'https://localhost/iclock/cdata?SN=TEST1&table=ATTLOG', server: [
        'REMOTE_ADDR' => '203.0.113.10', 'HTTPS' => 'on',
    ], content: 'invalid')->assertStatus(503);
});
