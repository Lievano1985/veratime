<?php

namespace App\Http\Controllers\Scheduling;

use App\Domains\Scheduling\Actions\GenerateDailyScheduleCsvTemplateAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Models\ScheduleBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DailyScheduleCsvTemplateController extends Controller
{
    public function __invoke(Request $request, GenerateDailyScheduleCsvTemplateAction $action, CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);

        $batch = null;
        if ($request->filled('schedule_batch_id')) {
            $batch = ScheduleBatch::query()
                ->where('company_id', $company->id)
                ->with(['company', 'center', 'dailyAssignments'])
                ->findOrFail((int) $request->integer('schedule_batch_id'));

            Gate::authorize('update', $batch);
        } else {
            Gate::authorize('create', [ScheduleBatch::class, $company]);
        }

        return $action->handle($company, $batch, [
            'worker_search' => $request->string('worker_search')->toString(),
            'organizational_unit_id' => $request->input('organizational_unit_id'),
        ]);
    }
}
