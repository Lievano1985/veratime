<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\TimeRecords\Actions\ApproveManualTimeEventAction;
use App\Domains\TimeRecords\Actions\RegisterApiTimeEventAction;
use App\Domains\TimeRecords\Actions\RejectManualTimeEventAction;
use App\Domains\TimeRecords\Actions\VoidTimeEventAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListTimeEventsRequest;
use App\Http\Requests\Api\V1\RejectManualTimeEventRequest;
use App\Http\Requests\Api\V1\StoreTimeEventRequest;
use App\Http\Requests\Api\V1\VoidTimeEventRequest;
use App\Http\Resources\Api\V1\TimeEventResource;
use App\Models\Company;
use App\Models\TimeEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TimeEventController extends Controller
{
    public function index(ListTimeEventsRequest $request): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');

        Gate::authorize('viewAny', [TimeEvent::class, $company]);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 25);
        $events = $company->timeEvents()->with('worker')
            ->when($filters['worker_id'] ?? null, fn ($query, $id) => $query->where('worker_id', $id))
            ->when($filters['employee_code'] ?? null, fn ($query, $code) => $query->whereHas('worker', fn ($workers) => $workers->where('employee_code', $code)))
            ->when($filters['center_id'] ?? null, fn ($query, $id) => $query->where('center_id', $id))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('occurred_local_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('occurred_local_date', '<=', $date))
            ->when($filters['event_type'] ?? null, fn ($query, $type) => $query->where('event_type', $type))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('occurred_at_utc')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'data' => TimeEventResource::collection($events->getCollection())->resolve(),
            'meta' => ['current_page' => $events->currentPage(), 'per_page' => $events->perPage(), 'total' => $events->total(), 'trace_id' => $request->attributes->get('api.trace_id')],
            'links' => ['first' => $events->url(1), 'last' => $events->url($events->lastPage()), 'prev' => $events->previousPageUrl(), 'next' => $events->nextPageUrl()],
        ]);
    }

    public function store(StoreTimeEventRequest $request, RegisterApiTimeEventAction $action): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');

        Gate::authorize('create', [TimeEvent::class, $company]);

        try {
            $result = $action->handle($company, $request->user(), [
                ...$request->validated(),
                'idempotency_key' => $request->header('Idempotency-Key'),
                'trace_id' => $request->attributes->get('api.trace_id'),
            ]);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['event' => $exception->getMessage()]);
        }

        return response()->json([
            'data' => (new TimeEventResource($result['event']))->resolve(),
            'meta' => [
                'trace_id' => $request->attributes->get('api.trace_id'),
                'idempotent_replay' => ! $result['created'],
            ],
        ], $result['created'] ? 201 : 200);
    }

    public function show(Request $request, int $eventId): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');
        $event = $company->timeEvents()->with('worker')->findOrFail($eventId);

        Gate::authorize('view', $event);

        return response()->json([
            'data' => (new TimeEventResource($event))->resolve(),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }

    public function void(VoidTimeEventRequest $request, int $eventId, VoidTimeEventAction $action): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');
        $event = $company->timeEvents()->findOrFail($eventId);

        try {
            $event = $action->handle($event, $request->user(), $request->validated('reason'));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        return response()->json([
            'data' => (new TimeEventResource($event))->resolve(),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }

    public function approve(Request $request, int $eventId, ApproveManualTimeEventAction $action): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');
        $event = $company->timeEvents()->findOrFail($eventId);

        try {
            $event = $action->handle($event, $request->user());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['event' => $exception->getMessage()]);
        }

        return response()->json([
            'data' => (new TimeEventResource($event))->resolve(),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }

    public function reject(RejectManualTimeEventRequest $request, int $eventId, RejectManualTimeEventAction $action): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');
        $event = $company->timeEvents()->findOrFail($eventId);

        try {
            $event = $action->handle($event, $request->user(), $request->validated('reason'));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        return response()->json([
            'data' => (new TimeEventResource($event))->resolve(),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }
}
