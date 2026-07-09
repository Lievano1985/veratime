<?php

namespace App\Providers;

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Center;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\LaborCondition;
use App\Models\Worker;
use App\Models\WorkerCredential;
use App\Policies\CenterPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\EmploymentRelationshipPolicy;
use App\Policies\LaborConditionPolicy;
use App\Policies\WorkerPolicy;
use App\Policies\WorkerCredentialPolicy;
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
        Gate::policy(Center::class, CenterPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(EmploymentRelationship::class, EmploymentRelationshipPolicy::class);
        Gate::policy(LaborCondition::class, LaborConditionPolicy::class);
        Gate::policy(Worker::class, WorkerPolicy::class);
        Gate::policy(WorkerCredential::class, WorkerCredentialPolicy::class);
        View::addNamespace('layouts', resource_path('views/components/layouts'));
    }
}
