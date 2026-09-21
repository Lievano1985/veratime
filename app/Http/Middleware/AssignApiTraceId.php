<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AssignApiTraceId
{
    public function handle(Request $request, Closure $next): Response
    {
        $traceId = 'trc_'.Str::ulid()->toBase32();

        $request->attributes->set('api.trace_id', $traceId);

        $response = $next($request);
        $response->headers->set('X-Trace-Id', $traceId);

        return $response;
    }
}
