<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Alerts\Actions\ListAlertsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListAlertsRequest;
use App\Http\Resources\Api\V1\AlertResource;
use App\Models\Alert;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AlertController extends Controller
{
    public function index(ListAlertsRequest $request, ListAlertsAction $action): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');

        Gate::authorize('viewAny', [Alert::class, $company]);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 25);
        unset($filters['per_page']);
        $alerts = $action->handle($company, $filters, $perPage)->withQueryString();

        return response()->json([
            'data' => AlertResource::collection($alerts->getCollection())->resolve(),
            'meta' => [
                'current_page' => $alerts->currentPage(),
                'per_page' => $alerts->perPage(),
                'total' => $alerts->total(),
                'trace_id' => $request->attributes->get('api.trace_id'),
            ],
            'links' => [
                'first' => $alerts->url(1),
                'last' => $alerts->url($alerts->lastPage()),
                'prev' => $alerts->previousPageUrl(),
                'next' => $alerts->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, int $alertId): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');
        $alert = Alert::query()
            ->with(['alertType', 'worker', 'workDay.center', 'workDay.employmentRelationship'])
            ->where('company_id', $company->id)
            ->findOrFail($alertId);

        Gate::authorize('view', $alert);

        return response()->json([
            'data' => (new AlertResource($alert))->resolve(),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }
}
