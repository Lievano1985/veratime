<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Alerts\Actions\ListAlertsAction;
use App\Domains\Integrations\Actions\RevokePersonalApiTokenAction;
use App\Domains\Scheduling\Actions\ListPersonalScheduleAction;
use App\Domains\TimeRecords\Actions\RegisterPersonalTimeEventAction;
use App\Domains\TimeRecords\Actions\ResolvePersonalMarkingSecurityAction;
use App\Domains\TimeRecords\Actions\SyncPersonalTimeEventsAction;
use App\Domains\Workers\Actions\BuildPersonalMobileContextAction;
use App\Domains\Workers\Actions\CompleteMobileDeviceBindingAction;
use App\Domains\Workers\Actions\ListPersonalMobileDeviceBindingsAction;
use App\Domains\Workers\Actions\ResolvePersonalWorkerAction;
use App\Domains\Workers\Actions\StartMobileDeviceBindingChallengeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompleteMobileDeviceBindingRequest;
use App\Http\Requests\Api\V1\ListPersonalAlertsRequest;
use App\Http\Requests\Api\V1\ListPersonalScheduleRequest;
use App\Http\Requests\Api\V1\ListPersonalTimeEventsRequest;
use App\Http\Requests\Api\V1\ListPersonalWorkDaysRequest;
use App\Http\Requests\Api\V1\ShowPersonalMarkingSecurityRequest;
use App\Http\Requests\Api\V1\StartMobileDeviceBindingChallengeRequest;
use App\Http\Requests\Api\V1\StorePersonalTimeEventRequest;
use App\Http\Requests\Api\V1\SyncPersonalTimeEventsRequest;
use App\Http\Resources\Api\V1\AlertResource;
use App\Http\Resources\Api\V1\MobileDeviceBindingResource;
use App\Http\Resources\Api\V1\PersonalScheduleResource;
use App\Http\Resources\Api\V1\TimeEventResource;
use App\Http\Resources\Api\V1\WorkDayResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class PersonalTimeController extends Controller
{
    public function context(Request $request, ResolvePersonalWorkerAction $resolve, BuildPersonalMobileContextAction $context): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $worker = $resolve->handle($request->user(), $company);

        return response()->json([
            'data' => $context->handle($company, $worker),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }

    public function markingSecurity(ShowPersonalMarkingSecurityRequest $request, ResolvePersonalWorkerAction $resolve, ResolvePersonalMarkingSecurityAction $action): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $worker = $resolve->handle($request->user(), $company);

        return response()->json([
            'data' => $action->handle($company, $request->user(), $worker),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }

    public function deviceBindingChallenge(StartMobileDeviceBindingChallengeRequest $request, ResolvePersonalWorkerAction $resolve, StartMobileDeviceBindingChallengeAction $action): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $worker = $resolve->handle($request->user(), $company);

        try {
            $result = $action->handle($company, $request->user(), $worker, $request->validated('authorization_code'), $request->validated('device_name'));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['authorization_code' => $exception->getMessage()]);
        }

        return response()->json([
            'data' => [
                'authorization_id' => $result['authorization']->public_id,
                'challenge' => $result['challenge'],
                'expires_at' => $result['authorization']->challenge_expires_at?->toIso8601String(),
                'algorithm' => 'ES256',
                'signature_format' => 'der',
                'payload' => $result['payload'],
            ],
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }

    public function completeDeviceBinding(CompleteMobileDeviceBindingRequest $request, ResolvePersonalWorkerAction $resolve, CompleteMobileDeviceBindingAction $action): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $worker = $resolve->handle($request->user(), $company);
        try {
            $binding = $action->handle($company, $request->user(), $worker, $request->validated('authorization_id'), $request->validated('public_key_spki'), $request->validated('signature'));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['binding' => $exception->getMessage()]);
        }

        return response()->json(['data' => ['binding' => ['id' => $binding->public_id, 'status' => $binding->status, 'device_name' => $binding->device_name, 'activated_at' => $binding->activated_at?->toIso8601String()]], 'meta' => ['trace_id' => $request->attributes->get('api.trace_id')]], 201);
    }

    public function deviceBindings(Request $request, ResolvePersonalWorkerAction $resolve, ListPersonalMobileDeviceBindingsAction $action): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $worker = $resolve->handle($request->user(), $company);

        return response()->json([
            'data' => MobileDeviceBindingResource::collection($action->handle($company, $request->user(), $worker))->resolve(),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }

    public function events(ListPersonalTimeEventsRequest $request, ResolvePersonalWorkerAction $resolve): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $worker = $resolve->handle($request->user(), $company);
        $filters = $request->validated();
        $events = $company->timeEvents()
            ->with('worker')
            ->where('worker_id', $worker->id)
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('occurred_local_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('occurred_local_date', '<=', $date))
            ->when($filters['event_type'] ?? null, fn ($query, $type) => $query->where('event_type', $type))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('occurred_at_utc')
            ->paginate($this->perPage($filters))
            ->withQueryString();

        return $this->paginated($request, $events, TimeEventResource::class);
    }

    public function schedule(ListPersonalScheduleRequest $request, ResolvePersonalWorkerAction $resolve, ListPersonalScheduleAction $action): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $worker = $resolve->handle($request->user(), $company);
        $schedule = $action->handle($company, $worker, $request->validated());

        return response()->json([
            'data' => PersonalScheduleResource::collection($schedule)->resolve(),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }

    public function alerts(ListPersonalAlertsRequest $request, ResolvePersonalWorkerAction $resolve, ListAlertsAction $action): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $worker = $resolve->handle($request->user(), $company);
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 25);
        unset($filters['per_page']);
        $alerts = $action->handle($company, [...$filters, 'worker_id' => $worker->id], $perPage)->withQueryString();

        return $this->paginated($request, $alerts, AlertResource::class);
    }

    public function workDays(ListPersonalWorkDaysRequest $request, ResolvePersonalWorkerAction $resolve): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $worker = $resolve->handle($request->user(), $company);
        $filters = $request->validated();
        $days = $worker->workDays()
            ->with(['worker', 'center', 'activeCalculation'])
            ->where('company_id', $company->id)
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('work_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('work_date', '<=', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('work_date')
            ->paginate($this->perPage($filters))
            ->withQueryString();

        return $this->paginated($request, $days, WorkDayResource::class);
    }

    public function event(Request $request, int $eventId, ResolvePersonalWorkerAction $resolve): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $worker = $resolve->handle($request->user(), $company);
        $event = $company->timeEvents()->where('worker_id', $worker->id)->findOrFail($eventId);

        return response()->json(['data' => (new TimeEventResource($event))->resolve(), 'meta' => ['trace_id' => $request->attributes->get('api.trace_id')]]);
    }

    public function storeEvent(StorePersonalTimeEventRequest $request, ResolvePersonalWorkerAction $resolve, RegisterPersonalTimeEventAction $action): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $worker = $resolve->handle($request->user(), $company);

        try {
            $result = $action->handle($company, $request->user(), $worker, [
                ...$request->validated(),
                'idempotency_key' => trim((string) $request->header('Idempotency-Key')),
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

    public function syncEvents(SyncPersonalTimeEventsRequest $request, ResolvePersonalWorkerAction $resolve, SyncPersonalTimeEventsAction $action): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $worker = $resolve->handle($request->user(), $company);
        $results = $action->handle($company, $request->user(), $worker, $request->validated('events'), $request->attributes->get('api.trace_id'));

        return response()->json([
            'data' => array_map(fn (array $result): array => [
                'client_event_id' => $result['client_event_id'],
                'status' => $result['status'],
                'event' => isset($result['event']) ? (new TimeEventResource($result['event']))->resolve() : null,
                'error' => $result['error'] ?? null,
                'error_code' => $result['error_code'] ?? null,
                'retryable' => $result['retryable'] ?? false,
                'retain_local' => $result['retain_local'] ?? false,
            ], $results),
            'meta' => [
                'accepted' => count(array_filter($results, fn (array $result): bool => $result['status'] === 'accepted')),
                'already_registered' => count(array_filter($results, fn (array $result): bool => $result['status'] === 'already_registered')),
                'rejected' => count(array_filter($results, fn (array $result): bool => $result['status'] === 'rejected')),
                'conflict' => count(array_filter($results, fn (array $result): bool => $result['status'] === 'conflict')),
                'trace_id' => $request->attributes->get('api.trace_id'),
            ],
        ]);
    }

    public function workDay(Request $request, int $workDayId, ResolvePersonalWorkerAction $resolve): JsonResponse
    {
        /** @var Company $company */ $company = $request->attributes->get('api.company');
        $worker = $resolve->handle($request->user(), $company);
        $day = $worker->workDays()->with(['worker', 'center', 'activeCalculation'])->where('company_id', $company->id)->findOrFail($workDayId);

        return response()->json(['data' => (new WorkDayResource($day))->resolve(), 'meta' => ['trace_id' => $request->attributes->get('api.trace_id')]]);
    }

    public function revokeToken(Request $request, RevokePersonalApiTokenAction $action): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');
        $token = $request->user()?->currentAccessToken();

        abort_unless($token instanceof PersonalAccessToken, 403, 'Se requiere una credencial personal revocable.');
        $action->handle($request->user(), $company, $token);

        return response()->json(null, 204);
    }

    /** @param array<string, mixed> $filters */
    private function perPage(array $filters): int
    {
        return min(100, max(1, (int) ($filters['per_page'] ?? 25)));
    }

    /** @param class-string<AlertResource|TimeEventResource|WorkDayResource> $resource */
    private function paginated(Request $request, LengthAwarePaginator $items, string $resource): JsonResponse
    {
        return response()->json([
            'data' => $resource::collection($items->getCollection())->resolve(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'trace_id' => $request->attributes->get('api.trace_id'),
            ],
            'links' => [
                'first' => $items->url(1),
                'last' => $items->url($items->lastPage()),
                'prev' => $items->previousPageUrl(),
                'next' => $items->nextPageUrl(),
            ],
        ]);
    }
}
