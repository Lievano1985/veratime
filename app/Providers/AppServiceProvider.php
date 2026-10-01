<?php

namespace App\Providers;

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Alert;
use App\Models\AttendanceIncident;
use App\Models\AttendancePeriod;
use App\Models\Center;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\KioskDevice;
use App\Models\LaborCondition;
use App\Models\MandatoryRestDay;
use App\Models\OperationalScopeAssignment;
use App\Models\OrganizationalUnit;
use App\Models\PayrollExportTemplate;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleProfile;
use App\Models\ShiftTemplate;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\Worker;
use App\Models\WorkerCredential;
use App\Policies\AlertPolicy;
use App\Policies\AttendanceIncidentPolicy;
use App\Policies\AttendancePeriodPolicy;
use App\Policies\CenterPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\CustomerAccountPolicy;
use App\Policies\EmploymentRelationshipPolicy;
use App\Policies\EmploymentUnitAssignmentPolicy;
use App\Policies\KioskDevicePolicy;
use App\Policies\LaborConditionPolicy;
use App\Policies\MandatoryRestDayPolicy;
use App\Policies\OperationalScopeAssignmentPolicy;
use App\Policies\OrganizationalUnitPolicy;
use App\Policies\PayrollExportTemplatePolicy;
use App\Policies\ScheduleAssignmentPolicy;
use App\Policies\SchedulePolicy;
use App\Policies\ScheduleProfilePolicy;
use App\Policies\ShiftTemplatePolicy;
use App\Policies\TimeEventPolicy;
use App\Policies\UserPolicy;
use App\Policies\WorkDayPolicy;
use App\Policies\WorkerCredentialPolicy;
use App\Policies\WorkerPolicy;
use App\Support\RoleKey;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        RateLimiter::for('api', function ($request): Limit {
            $token = $request->user()?->currentAccessToken();
            $tokenId = $token && method_exists($token, 'getKey') ? $token->getKey() : $request->ip();
            $companyId = $request->attributes->get('api.company')?->id ?? 'unknown';

            return Limit::perMinute(60)->by($companyId.'|'.$tokenId);
        });

        RateLimiter::for('mobile-login', function ($request): Limit {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('mobile-password-reset', function ($request): Limit {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        Gate::define('manageApiTokens', function (User $user, Company $company): bool {
            return $company->status === 'active'
                && $user->belongsToCompany($company)
                && in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true);
        });

        Gate::policy(Alert::class, AlertPolicy::class);
        Gate::policy(AttendanceIncident::class, AttendanceIncidentPolicy::class);
        Gate::policy(AttendancePeriod::class, AttendancePeriodPolicy::class);
        Gate::policy(Center::class, CenterPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(CustomerAccount::class, CustomerAccountPolicy::class);
        Gate::policy(EmploymentRelationship::class, EmploymentRelationshipPolicy::class);
        Gate::policy(EmploymentUnitAssignment::class, EmploymentUnitAssignmentPolicy::class);
        Gate::policy(LaborCondition::class, LaborConditionPolicy::class);
        Gate::policy(KioskDevice::class, KioskDevicePolicy::class);
        Gate::policy(MandatoryRestDay::class, MandatoryRestDayPolicy::class);
        Gate::policy(OperationalScopeAssignment::class, OperationalScopeAssignmentPolicy::class);
        Gate::policy(OrganizationalUnit::class, OrganizationalUnitPolicy::class);
        Gate::policy(PayrollExportTemplate::class, PayrollExportTemplatePolicy::class);
        Gate::policy(Schedule::class, SchedulePolicy::class);
        Gate::policy(ScheduleAssignment::class, ScheduleAssignmentPolicy::class);
        Gate::policy(ScheduleProfile::class, ScheduleProfilePolicy::class);
        Gate::policy(ShiftTemplate::class, ShiftTemplatePolicy::class);
        Gate::policy(TimeEvent::class, TimeEventPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(WorkDay::class, WorkDayPolicy::class);
        Gate::policy(Worker::class, WorkerPolicy::class);
        Gate::policy(WorkerCredential::class, WorkerCredentialPolicy::class);
        View::addNamespace('layouts', resource_path('views/components/layouts'));
    }
}
