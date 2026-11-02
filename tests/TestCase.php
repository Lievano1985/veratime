<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = (string) $app['config']->get('database.connections.'.$connection.'.database');
        if ($database !== ':memory:' && ! preg_match('/^vt_audit_[a-z0-9_]+$/i', $database)) {
            throw new \RuntimeException('Tests blocked: database must be :memory: or an isolated vt_audit_ database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }
}
