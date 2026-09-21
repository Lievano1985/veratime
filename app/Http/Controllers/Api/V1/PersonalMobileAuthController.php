<?php

namespace App\Http\Controllers\Api\V1;

use App\Domains\Identity\Actions\RequestPasswordResetAction;
use App\Domains\Integrations\Actions\IssueCompanyApiTokenAction;
use App\Domains\Workers\Actions\AuthenticatePersonalMobileUserAction;
use App\Domains\Workers\Actions\BuildPersonalMobileContextAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PersonalMobileLoginRequest;
use App\Http\Requests\Api\V1\RequestPersonalPasswordResetRequest;
use Illuminate\Http\JsonResponse;

class PersonalMobileAuthController extends Controller
{
    public function forgotPassword(RequestPersonalPasswordResetRequest $request, RequestPasswordResetAction $resetPassword): JsonResponse
    {
        $resetPassword->handle($request->validated('email'));

        return response()->json([
            'message' => 'Si la cuenta existe, se enviaron instrucciones para restablecer la contraseña.',
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ], 202);
    }

    public function login(
        PersonalMobileLoginRequest $request,
        AuthenticatePersonalMobileUserAction $authenticate,
        IssueCompanyApiTokenAction $issueToken,
        BuildPersonalMobileContextAction $context,
    ): JsonResponse {
        $credentials = $request->validated();
        $result = $authenticate->handle($credentials['email'], $credentials['password'], $credentials['company_id'] ?? null);

        if (! $result['link']) {
            return response()->json([
                'message' => 'Selecciona la empresa de VERA Time que deseas usar.',
                'code' => 'company_selection_required',
                'data' => ['companies' => $result['companies']],
                'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
            ], 409);
        }

        $company = $result['link']->company;
        $worker = $result['link']->worker;
        $abilities = ['self:read', 'self:write'];
        $issued = $issueToken->handle($result['user'], $company, 'pwa-personal', $abilities);

        return response()->json([
            'data' => [
                'token' => $issued->plainTextToken,
                'token_type' => 'Bearer',
                'abilities' => $abilities,
                'context' => $context->handle($company, $worker),
            ],
            'meta' => ['trace_id' => $request->attributes->get('api.trace_id')],
        ], 201);
    }
}
