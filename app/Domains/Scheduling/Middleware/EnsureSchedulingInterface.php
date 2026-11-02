<?php

namespace App\Domains\Scheduling\Middleware;

use App\Domains\Scheduling\Support\SchedulingInterface;
use App\Domains\Tenancy\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;

class EnsureSchedulingInterface
{
    public function handle(Request $request, Closure $next)
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($company, 403);
        $required = $request->route()?->getName() === 'scheduling.planner' ? SchedulingInterface::PLANNER : SchedulingInterface::CLASSIC;
        abort_unless(SchedulingInterface::forCompany($company) === $required, 403, 'Este modelo de horarios no está activo para la empresa. Contacta al superadministrador.');

        return $next($request);
    }
}
