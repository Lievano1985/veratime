<?php

namespace App\Http\Controllers\Scheduling;

use App\Domains\Scheduling\Actions\GenerateDailyScheduleCsvErrorReportAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Http\Controllers\Controller;
use App\Models\ImportBatch;

class DailyScheduleCsvErrorReportController extends Controller
{
    public function __invoke(ImportBatch $importBatch, GenerateDailyScheduleCsvErrorReportAction $action, CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);

        return $action->handle(auth()->user(), $company, $importBatch);
    }
}
