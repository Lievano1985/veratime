<?php

namespace App\Domains\Tenancy\Middleware;

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ResolveApiTokenCompany
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        $companyId = $token instanceof PersonalAccessToken ? $token->company_id : null;

        if (! $user || ! $token instanceof PersonalAccessToken || $user->status !== 'active' || ! $companyId) {
            throw new HttpException(403, 'El token no tiene una empresa asignada.');
        }

        $company = Company::query()
            ->with('customerAccount')
            ->whereKey($companyId)
            ->where('status', 'active')
            ->first();

        if (! $company || ! $user->belongsToCompany($company)) {
            throw new HttpException(403, 'El token no puede operar sobre esta empresa.');
        }

        $this->currentCompany->set($company);
        $request->attributes->set('api.company', $company);

        return $next($request);
    }
}
