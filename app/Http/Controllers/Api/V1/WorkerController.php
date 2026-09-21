<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Workers\Actions\SaveWorkerWithEmploymentRelationshipAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWorkerRequest;
use App\Http\Requests\Api\V1\UpdateWorkerRequest;
use App\Http\Resources\Api\V1\EmploymentRelationshipResource;
use App\Http\Resources\Api\V1\WorkerResource;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Worker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WorkerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');

        Gate::authorize('viewAny', [Worker::class, $company]);

        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);
        $query = $company->workers()->with('activeEmploymentRelationship.center');

        if ($status = $request->string('status')->trim()->value()) {
            $query->where('status', $status);
        }

        if ($employeeCode = $request->string('employee_code')->trim()->value()) {
            $query->where('employee_code', $employeeCode);
        }

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(fn ($workers) => $workers
                ->where('full_name', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%"));
        }

        $workers = $query->orderBy('full_name')->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => WorkerResource::collection($workers->getCollection())->resolve(),
            'meta' => [
                'current_page' => $workers->currentPage(),
                'per_page' => $workers->perPage(),
                'total' => $workers->total(),
                'trace_id' => $request->attributes->get('api.trace_id'),
            ],
            'links' => [
                'first' => $workers->url(1),
                'last' => $workers->url($workers->lastPage()),
                'prev' => $workers->previousPageUrl(),
                'next' => $workers->nextPageUrl(),
            ],
        ]);
    }

    public function store(StoreWorkerRequest $request, SaveWorkerWithEmploymentRelationshipAction $action): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');
        $data = $request->validated();
        $center = $company->centers()->whereKey($data['center_id'])->firstOrFail();

        Gate::authorize('create', [Worker::class, $company]);
        Gate::authorize('createForCenter', [Worker::class, $company, $center]);
        Gate::authorize('create', [EmploymentRelationship::class, $company, $center]);

        $worker = $action->handle($company, null, $center, [
            ...$data,
            'source' => 'api',
            'relationship_source' => 'api',
            'status' => 'active',
        ], $request->user());
        $worker->load('activeEmploymentRelationship.center');

        return response()->json([
            'data' => (new WorkerResource($worker))->resolve(),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ], 201);
    }

    public function show(Request $request, int $workerId): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');

        Gate::authorize('viewAny', [Worker::class, $company]);

        $worker = $company->workers()
            ->with('activeEmploymentRelationship.center')
            ->findOrFail($workerId);

        return response()->json([
            'data' => (new WorkerResource($worker))->resolve(),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }

    public function relationships(Request $request, int $workerId): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');

        Gate::authorize('viewAny', [Worker::class, $company]);

        $worker = $company->workers()->findOrFail($workerId);
        $relationships = $worker->employmentRelationships()
            ->with('center')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => EmploymentRelationshipResource::collection($relationships)->resolve(),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }

    public function update(UpdateWorkerRequest $request, int $workerId, SaveWorkerWithEmploymentRelationshipAction $action): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');
        $worker = $company->workers()->with('activeEmploymentRelationship')->findOrFail($workerId);
        $data = $request->validated();
        $center = $company->centers()->whereKey($data['center_id'])->where('status', 'active')->firstOrFail();

        Gate::authorize('update', $worker);
        Gate::authorize('createForCenter', [Worker::class, $company, $center]);
        Gate::authorize('create', [EmploymentRelationship::class, $company, $center]);

        if ($relationship = $worker->activeEmploymentRelationship) {
            Gate::authorize('update', $relationship);
        }

        try {
            $worker = $action->handle($company, $worker, $center, [
                ...$data,
                'relationship_source' => 'api',
            ], $request->user());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'relationship_change_reason' => $exception->getMessage(),
            ]);
        }

        $worker->load('activeEmploymentRelationship.center');

        return response()->json([
            'data' => (new WorkerResource($worker))->resolve(),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }
}
