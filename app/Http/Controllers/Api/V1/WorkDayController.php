<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\WorkDays\Actions\ListWorkDaysAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListWorkDaysRequest;
use App\Http\Resources\Api\V1\WorkDayResource;
use App\Models\Company;
use App\Models\WorkDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkDayController extends Controller
{
    public function index(ListWorkDaysRequest $request, ListWorkDaysAction $action): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 25);
        unset($filters['per_page']);

        $workDays = $action->handle($company, $filters, $perPage);

        return $this->paginated($request, $workDays);
    }

    public function show(Request $request, int $workDayId): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');
        $workDay = WorkDay::query()
            ->where('company_id', $company->id)
            ->with(['worker', 'center', 'employmentRelationship', 'activeCalculation'])
            ->findOrFail($workDayId);

        Gate::authorize('view', $workDay);

        return response()->json([
            'data' => (new WorkDayResource($workDay))->resolve(),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }

    private function paginated(ListWorkDaysRequest $request, $workDays): JsonResponse
    {
        return response()->json([
            'data' => WorkDayResource::collection($workDays->getCollection())->resolve(),
            'meta' => [
                'current_page' => $workDays->currentPage(),
                'per_page' => $workDays->perPage(),
                'total' => $workDays->total(),
                'trace_id' => $request->attributes->get('api.trace_id'),
            ],
            'links' => [
                'first' => $workDays->url(1),
                'last' => $workDays->url($workDays->lastPage()),
                'prev' => $workDays->previousPageUrl(),
                'next' => $workDays->nextPageUrl(),
            ],
        ]);
    }
}
