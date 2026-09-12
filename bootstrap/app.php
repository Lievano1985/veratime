<?php

use App\Domains\Tenancy\Middleware\EnsureCurrentCompany;
use App\Domains\Products\Middleware\EnsureProductAccess;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
