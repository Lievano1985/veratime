<?php

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
