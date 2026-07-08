<?php

namespace App\Domains\Tenancy\Middleware;

use App\Domains\Tenancy\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentCompany
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $companyId = session('current_company_id');

        $company = $companyId
            ? $user->activeCompanies()->whereKey($companyId)->first()
            : null;

        if ($companyId && ! $company) {
            session()->forget('current_company_id');
        }

        $company ??= $user->defaultCompany();

        if ($company) {
            session(['current_company_id' => $company->id]);
            $this->currentCompany->set($company);

            return $next($request);
        }

        session()->forget('current_company_id');
        $this->currentCompany->clear();

        throw new HttpException(403, 'No active company is assigned to this user.');
    }
}
