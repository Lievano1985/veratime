<?php

namespace App\Providers;

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use App\Policies\CompanyPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentCompany::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Company::class, CompanyPolicy::class);
        View::addNamespace('layouts', resource_path('views/components/layouts'));
    }
}
