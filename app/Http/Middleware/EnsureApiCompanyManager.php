<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\RoleKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureApiCompanyManager
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Company|null $company */
        $company = $request->attributes->get('api.company');
        $user = $request->user();

        if (! $company || ! $user || ! in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            throw new HttpException(403, 'El token requiere un rol administrador en la empresa.');
        }

        return $next($request);
    }
}
