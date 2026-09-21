<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\AttendanceIncidents\Actions\CancelAttendanceIncidentAction;
use App\Domains\AttendanceIncidents\Actions\CreateAttendanceIncidentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CancelAttendanceIncidentRequest;
use App\Http\Requests\Api\V1\ListAttendanceIncidentsRequest;
use App\Http\Requests\Api\V1\StoreAttendanceIncidentRequest;
use App\Http\Resources\Api\V1\AttendanceIncidentResource;
use App\Models\AttendanceIncident;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AttendanceIncidentController extends Controller
{
    public function index(ListAttendanceIncidentsRequest $request): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        Gate::authorize('viewAny', [AttendanceIncident::class, $company]);
        $filters = $request->validated();
        $incidents = AttendanceIncident::query()->with('worker')->where('company_id', $company->id)
            ->when($filters['worker_id'] ?? null, fn ($query, $id) => $query->where('worker_id', $id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['incident_type'] ?? null, fn ($query, $type) => $query->where('incident_type', $type))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('start_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('end_date', '<=', $date))
            ->latest('start_date')->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();

        return response()->json(['data' => AttendanceIncidentResource::collection($incidents->getCollection())->resolve(), 'meta' => ['current_page' => $incidents->currentPage(), 'per_page' => $incidents->perPage(), 'total' => $incidents->total(), 'trace_id' => $request->attributes->get('api.trace_id')], 'links' => ['first' => $incidents->url(1), 'last' => $incidents->url($incidents->lastPage()), 'prev' => $incidents->previousPageUrl(), 'next' => $incidents->nextPageUrl()]]);
    }

    public function store(StoreAttendanceIncidentRequest $request, CreateAttendanceIncidentAction $action): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        Gate::authorize('create', [AttendanceIncident::class, $company]);
        try {
            $incident = $action->handle($company, $request->user(), $request->validated());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['incident' => $e->getMessage()]);
        }

        return response()->json(['data' => (new AttendanceIncidentResource($incident->load('worker')))->resolve(), 'meta' => ['trace_id' => $request->attributes->get('api.trace_id')]], 201);
    }

    public function show(Request $request, int $incidentId): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $incident = AttendanceIncident::query()->with('worker')->where('company_id', $company->id)->findOrFail($incidentId);
        Gate::authorize('view', $incident);

        return response()->json(['data' => (new AttendanceIncidentResource($incident))->resolve(), 'meta' => ['trace_id' => $request->attributes->get('api.trace_id')]]);
    }

    public function cancel(CancelAttendanceIncidentRequest $request, int $incidentId, CancelAttendanceIncidentAction $action): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $incident = AttendanceIncident::query()->where('company_id', $company->id)->findOrFail($incidentId);
        Gate::authorize('cancel', $incident);
        try {
            $incident = $action->handle($company, $incident, $request->user(), $request->validated('reason'));
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        return response()->json(['data' => (new AttendanceIncidentResource($incident->load('worker')))->resolve(), 'meta' => ['trace_id' => $request->attributes->get('api.trace_id')]]);
    }
}
