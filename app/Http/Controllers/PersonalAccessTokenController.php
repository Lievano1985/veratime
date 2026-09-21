<?php

namespace App\Http\Controllers;

use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Workers\Actions\ResolvePersonalWorkerAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalAccessTokenController extends Controller
{
    public function store(Request $request, CurrentCompany $currentCompany, ResolvePersonalWorkerAction $resolveWorker, IssueCompanyApiTokenAction $issue): JsonResponse
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);

        $resolveWorker->handle($request->user(), $company);
        $abilities = ['self:read', 'self:write'];
        $issued = $issue->handle($request->user(), $company, 'pwa-personal', $abilities);

        return response()->json([
            'data' => ['token' => $issued->plainTextToken, 'token_type' => 'Bearer', 'abilities' => $abilities],
        ], 201);
    }
}
