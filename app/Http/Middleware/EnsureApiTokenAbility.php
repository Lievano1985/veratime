<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureApiTokenAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token || ! $token->can($ability)) {
            throw new HttpException(403, 'El token no tiene el alcance requerido.');
        }

        return $next($request);
    }
}
