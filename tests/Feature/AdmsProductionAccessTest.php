<?php

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

it('restricts the production ADMS pilot to HTTPS and the configured serial and network address', function () {
    $this->app->instance('env', 'production');
    config(['adms.enabled' => false, 'adms.pilot_ip' => '203.0.113.10', 'adms.pilot_serial' => 'TEST1']);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10', 'HTTPS' => 'on']);
    $this->get('https://localhost/iclock/getrequest?SN=TEST1')->assertNotFound();

    config(['adms.enabled' => true]);
    $this->get('https://localhost/iclock/getrequest?SN=WRONG')->assertNotFound();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11', 'HTTPS' => 'on'])
        ->get('https://localhost/iclock/getrequest?SN=TEST1')->assertNotFound();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10', 'HTTPS' => 'off'])
        ->get('http://localhost/iclock/getrequest?SN=TEST1')->assertNotFound();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10', 'HTTPS' => 'on'])
        ->get('https://localhost/iclock/getrequest?SN=test1')->assertOk()->assertSeeText('OK');
});

it('logs an authorized rejected batch without writing the payload', function () {
    $this->app->instance('env', 'production');
    config(['adms.enabled' => true, 'adms.pilot_ip' => '203.0.113.10', 'adms.pilot_serial' => 'TEST1']);
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('warning')->once()->with('adms.importacion_rechazada', [
        'motivo' => 'reloj_empresa_o_centro_no_autorizado',
        'fila' => null,
        'id_en_reloj' => null,
        'estado' => null,
    ]);
    Log::shouldReceive('channel')->once()->with('adms')->andReturn($logger);

    $body = "1\t2026-11-13 08:00:00\t0";
    $this->call('POST', 'https://localhost/iclock/cdata?SN=TEST1&table=ATTLOG', server: [
        'REMOTE_ADDR' => '203.0.113.10',
        'CONTENT_LENGTH' => (string) strlen($body),
    ], content: $body)->assertStatus(503);
});
