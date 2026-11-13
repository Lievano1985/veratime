<?php

$device = getenv('VERA_ADMS_PROBE_DEVICE');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

if (PHP_SAPI !== 'cli-server' || ! $device || ($_SERVER['REMOTE_ADDR'] ?? '') !== $device
    || ! in_array($path, ['/iclock/cdata', '/iclock/getrequest'], true)) {
    http_response_code(404);
    return;
}

require __DIR__.'/../public/index.php';
