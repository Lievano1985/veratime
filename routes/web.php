<?php

use App\Http\Controllers\Attendance\AttendancePeriodPayrollCsvController;
use App\Http\Controllers\Attendance\AttendancePeriodPayrollXlsxController;
use App\Http\Controllers\CompanyBrandingImageController;
use App\Http\Controllers\Dashboard\OperationalDashboardController;
use App\Http\Controllers\Marketing\DemoRequestController;
use App\Http\Controllers\PersonalAccessTokenController;
use App\Http\Controllers\Scheduling\DailyScheduleCsvErrorReportController;
use App\Http\Controllers\Scheduling\DailyScheduleCsvTemplateController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::post('demo-requests', [DemoRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('demo-requests.store');
Route::get('company-branding/{company}', CompanyBrandingImageController::class)
    ->name('company-branding.show');
Route::prefix('time')->group(function (): void {
    Volt::route('kiosk', 'kiosk.index')->name('kiosk.index');
    Volt::route('kiosk/authorize', 'kiosk.authorize')->name('kiosk.authorize');
});

Route::middleware(['auth'])->group(function () {
    Volt::route('customer-accounts', 'customer-accounts.index')->name('customer-accounts.index');
    Volt::route('companies', 'companies.index')->name('companies.index');
});

Route::middleware(['auth', 'current.company'])->group(function () {
    Route::prefix('time')->middleware('product:time')->group(function (): void {
        Route::post('personal-access-token', [PersonalAccessTokenController::class, 'store'])->name('personal-access-token.store');
        Route::get('dashboard', OperationalDashboardController::class)
            ->middleware('verified')
            ->name('dashboard');
        Volt::route('my-day', 'personal.my-day')->name('personal.my-day');
        Volt::route('users', 'users.index')->name('users.index');
        Volt::route('api-tokens', 'api-tokens.index')->name('api-tokens.index');
        Volt::route('company-settings', 'company-settings.index')->name('company-settings.index');
        Volt::route('configuration/csv-periodos', 'payroll-export-templates.index')->name('payroll-export-templates.index');
        Volt::route('centers', 'centers.index')->name('centers.index');
        Volt::route('workers', 'workers.index')->name('workers.index');
        Volt::route('schedules', 'schedules.index')->name('schedules.index');
        Volt::route('scheduling/shifts', 'scheduling.shifts')->name('scheduling.shifts');
        Volt::route('scheduling/profiles', 'scheduling.profiles')->name('scheduling.profiles');
        Volt::route('scheduling/profile-assignments', 'scheduling.profile-assignments')->name('scheduling.profile-assignments');
        Volt::route('scheduling/daily', 'scheduling.daily')->name('scheduling.daily');
        Route::get('scheduling/daily/csv/template', DailyScheduleCsvTemplateController::class)->name('scheduling.daily.csv.template');
        Route::get('scheduling/daily/imports/{importBatch}/errors', DailyScheduleCsvErrorReportController::class)->name('scheduling.daily.imports.errors');
        Volt::route('schedule-assignments', 'schedule-assignments.index')->name('schedule-assignments.index');
        Volt::route('mandatory-rest-days', 'mandatory-rest-days.index')->name('mandatory-rest-days.index');
        Volt::route('organization/units', 'organization.units')->name('organization.units');
        Volt::route('organization/assignments', 'organization.assignments')->name('organization.assignments');
        Volt::route('organization/scopes', 'organization.scopes')->name('organization.scopes');
        Volt::route('organization/my-scope', 'organization.my-scope')->name('organization.my-scope');
        Volt::route('time-clock', 'time-clock.index')->name('time-clock.index');
        Volt::route('time-events/manual', 'time-events.manual')->name('time-events.manual');
        Volt::route('attendance-incidents', 'attendance-incidents.index')->name('attendance-incidents.index');
        Volt::route('work-days', 'work-days.index')->name('work-days.index');
        Volt::route('testing/quick-events', 'testing.quick-events')->name('testing.quick-events');
        Volt::route('alerts', 'alerts.index')->name('alerts.index');
        Volt::route('attendance-periods', 'attendance-periods.index')->name('attendance-periods.index');
        Route::get('attendance-periods/{attendancePeriod}/payroll-csv', AttendancePeriodPayrollCsvController::class)->name('attendance-periods.payroll-csv');
        Route::get('attendance-periods/{attendancePeriod}/payroll-xlsx', AttendancePeriodPayrollXlsxController::class)->name('attendance-periods.payroll-xlsx');
    });

    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
