<?php

// Tests must never load the application's cached local/production connection.
$cache = dirname(__DIR__).'/storage/framework/testing-config-unused.php';
putenv('APP_CONFIG_CACHE='.$cache);
$_ENV['APP_CONFIG_CACHE'] = $cache;
$_SERVER['APP_CONFIG_CACHE'] = $cache;
require dirname(__DIR__).'/vendor/autoload.php';
