<?php

use App\Domains\Products\Middleware\EnsureProductAccess;
use App\Domains\Tenancy\Middleware\EnsureCurrentCompany;
use App\Domains\Tenancy\Middleware\ResolveApiTokenCompany;
use App\Http\Middleware\AssignApiTraceId;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureApiCompanyManager;
use App\Http\Middleware\EnsureApiTokenAbility;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            EnsureActiveUser::class,
        ]);

        $middleware->alias([
            'current.company' => EnsureCurrentCompany::class,
            'product' => EnsureProductAccess::class,
            'api.tenant' => ResolveApiTokenCompany::class,
            'api.ability' => EnsureApiTokenAbility::class,
            'api.company-manager' => EnsureApiCompanyManager::class,
            'api.trace' => AssignApiTraceId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
