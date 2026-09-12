<?php

namespace App\Domains\Products\Middleware;

use App\Domains\Products\Support\ProductAccess;
use App\Domains\Tenancy\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureProductAccess
{
    public function __construct(
        private readonly CurrentCompany $currentCompany,
        private readonly ProductAccess $productAccess,
    ) {}

    public function handle(Request $request, Closure $next, string $productKey): Response
    {
        $company = $this->currentCompany->get();

        if (! $company) {
            throw new HttpException(403, 'No active company is assigned to this user.');
        }

        if (! $this->productAccess->companyHasOperationalProduct($company, $productKey)) {
            throw new HttpException(403, 'El producto solicitado no esta activo para esta cuenta cliente.');
        }

        return $next($request);
    }
}
