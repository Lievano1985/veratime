<?php

namespace App\Http\Controllers\Scheduling;

use App\Domains\Scheduling\Actions\GenerateDailyScheduleXlsxAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DailyScheduleXlsxTemplateController extends Controller
{
    public function __invoke(Request $request, CurrentCompany $currentCompany, GenerateDailyScheduleXlsxAction $action)
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);
        $batch = $company->scheduleBatches()->findOrFail($request->integer('schedule_batch_id'));
        Gate::authorize('update', $batch);

        return $action->handle($company, $batch, [
            'worker_search' => $request->string('worker_search')->toString(),
            'organizational_unit_id' => $request->input('organizational_unit_id'),
        ]);
    }
}
