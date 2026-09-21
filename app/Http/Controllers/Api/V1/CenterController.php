<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListCentersRequest;
use App\Http\Resources\Api\V1\CenterResource;
use App\Models\Center;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CenterController extends Controller
{
    public function index(ListCentersRequest $request): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');
        Gate::authorize('viewAny', [Center::class, $company]);

        $filters = $request->validated();
        $centers = $company->centers()
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $term = trim($search);

                $query->where(fn ($centerQuery) => $centerQuery
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%"));
            })
            ->orderBy('name')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        return response()->json([
            'data' => CenterResource::collection($centers->getCollection())->resolve(),
            'meta' => [
                'current_page' => $centers->currentPage(),
                'per_page' => $centers->perPage(),
                'total' => $centers->total(),
                'trace_id' => $request->attributes->get('api.trace_id'),
            ],
            'links' => [
                'first' => $centers->url(1),
                'last' => $centers->url($centers->lastPage()),
                'prev' => $centers->previousPageUrl(),
                'next' => $centers->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, int $centerId): JsonResponse
    {
        /** @var Company $company */
        $company = $request->attributes->get('api.company');
        $center = $company->centers()->findOrFail($centerId);
        Gate::authorize('view', $center);

        return response()->json([
            'data' => (new CenterResource($center))->resolve(),
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ]);
    }
}
