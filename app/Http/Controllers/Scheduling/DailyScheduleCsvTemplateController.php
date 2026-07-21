<?php

namespace App\Http\Controllers\Scheduling;

use App\Domains\Scheduling\Actions\GenerateDailyScheduleCsvTemplateAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Models\ScheduleBatch;
use Illuminate\Support\Facades\Gate;

class DailyScheduleCsvTemplateController extends Controller
{
    public function __invoke(GenerateDailyScheduleCsvTemplateAction $action, CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);

        Gate::authorize('create', [ScheduleBatch::class, $company]);

        return $action->handle();
    }
}
