<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AssignApiTraceId
{
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = 'trc_'.Str::ulid()->toBase32();

        $request->attributes->set('api.trace_id', $traceId);

        $isMarking = $request->isMethod('POST') && $request->is('api/v1/time/me/time-events', 'api/v1/time/me/time-events/sync');

        try {
            $response = $next($request);
        } catch (ValidationException $exception) {
            if ($isMarking) {
                Log::warning('Marcaje móvil rechazado por validación', [
                    'trace_id' => $traceId,
                    'fields' => array_keys($exception->errors()),
                ]);
            }

            throw $exception;
        }

        if ($isMarking && $response instanceof JsonResponse) {
            $body = $response->getData(true);
            $reason = $body['error']['code'] ?? $body['data']['review_reason'] ?? null;
            $reasons = array_column($body['data'] ?? [], 'error_code');

            if ($reason || $reasons || $response->getStatusCode() >= 400) {
                Log::warning('Resultado de marcaje móvil', [
                    'trace_id' => $traceId,
                    'status' => $response->getStatusCode(),
                    'reason' => $reason,
                    'batch_reasons' => array_values(array_unique($reasons)),
                    'fields' => array_keys($body['errors'] ?? []),
                ]);
            }
        }
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }
}
