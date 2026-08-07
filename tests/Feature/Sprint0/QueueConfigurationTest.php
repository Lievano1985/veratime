<?php

namespace Tests\Feature\Sprint0;

use App\Domains\System\Jobs\RecordQueueHealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
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

    public function test_scheduler_runs_work_days_queue_for_cpanel_cron(): void
    {
        $events = collect(Schedule::events())->map(fn ($event): string => $event->command ?? '');

        $this->assertTrue(
            $events->contains(fn (string $command): bool => str_contains($command, 'queue:work database')
                && str_contains($command, '--queue=work-days,default')
                && str_contains($command, '--stop-when-empty')),
        );
    }
}
