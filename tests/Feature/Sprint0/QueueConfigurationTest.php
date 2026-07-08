<?php

namespace Tests\Feature\Sprint0;

use App\Domains\System\Jobs\RecordQueueHealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QueueConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_queue_persists_and_processes_basic_job(): void
    {
        config(['queue.default' => 'database']);

        RecordQueueHealthCheck::dispatch('sprint0_queue_health_check');

        $this->assertDatabaseCount('jobs', 1);

        Artisan::call('queue:work', [
            '--once' => true,
            '--stop-when-empty' => true,
        ]);

        $this->assertTrue(Cache::get('sprint0_queue_health_check'));
        $this->assertDatabaseCount('jobs', 0);
    }
}
