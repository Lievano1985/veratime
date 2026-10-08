<?php

namespace App\Domains\Companies\Jobs;

use App\Domains\Companies\Actions\GenerateCompanyDemoScenarioAction;
use App\Models\CompanyDemoScenario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateCompanyDemoScenarioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 180;

    public function __construct(public readonly int $scenarioId) {}

    public function handle(GenerateCompanyDemoScenarioAction $action): void
    {
        $scenario = CompanyDemoScenario::query()->find($this->scenarioId);

        if (! $scenario || $scenario->status === CompanyDemoScenario::STATUS_COMPLETED) {
            return;
        }

        $action->handle($scenario);
    }

    public function failed(Throwable $exception): void
    {
        CompanyDemoScenario::query()
            ->whereKey($this->scenarioId)
            ->whereNot('status', CompanyDemoScenario::STATUS_COMPLETED)
            ->update([
                'status' => CompanyDemoScenario::STATUS_FAILED,
                'error_message' => mb_substr($exception->getMessage(), 0, 5000),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
