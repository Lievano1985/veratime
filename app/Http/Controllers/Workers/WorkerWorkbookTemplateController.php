<?php

namespace App\Http\Controllers\Workers;

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Workers\Actions\GenerateWorkerWorkbookAction;
use App\Http\Controllers\Controller;

class WorkerWorkbookTemplateController extends Controller
{
    public function __invoke(CurrentCompany $currentCompany, GenerateWorkerWorkbookAction $action)
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);

        return $action->handle(auth()->user(), $company);
    }
}
