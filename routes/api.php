<?php

use App\Http\Controllers\Api\V1\AlertController;
use App\Http\Controllers\Api\V1\AttendanceIncidentController;
use App\Http\Controllers\Api\V1\AttendancePeriodController;
use App\Http\Controllers\Api\V1\CenterController;
use App\Http\Controllers\Api\V1\PersonalMobileAuthController;
use App\Http\Controllers\Api\V1\PersonalTimeController;
use App\Http\Controllers\Api\V1\TimeEventController;
use App\Http\Controllers\Api\V1\WorkDayController;
use App\Http\Controllers\Api\V1\WorkerController;
use Illuminate\Support\Facades\Route;

Route::post('v1/time/auth/login', [PersonalMobileAuthController::class, 'login'])
    ->middleware(['api.trace', 'throttle:mobile-login']);
Route::post('v1/time/auth/forgot-password', [PersonalMobileAuthController::class, 'forgotPassword'])
    ->middleware(['api.trace', 'throttle:mobile-password-reset']);

Route::prefix('v1/time')
    ->middleware(['auth:sanctum', 'api.trace', 'api.tenant', 'api.company-manager', 'product:time', 'throttle:api'])
    ->group(function (): void {
        Route::get('centers', [CenterController::class, 'index'])->middleware('api.ability:centers:read');
        Route::get('centers/{centerId}', [CenterController::class, 'show'])->middleware('api.ability:centers:read');
        Route::get('workers', [WorkerController::class, 'index'])->middleware('api.ability:workers:read');
        Route::post('workers', [WorkerController::class, 'store'])->middleware('api.ability:workers:write');
        Route::get('workers/{workerId}', [WorkerController::class, 'show'])->middleware('api.ability:workers:read');
        Route::get('workers/{workerId}/relationships', [WorkerController::class, 'relationships'])->middleware('api.ability:workers:read');
        Route::get('alerts', [AlertController::class, 'index'])->middleware('api.ability:alerts:read');
        Route::get('alerts/{alertId}', [AlertController::class, 'show'])->middleware('api.ability:alerts:read');
        Route::get('attendance-incidents', [AttendanceIncidentController::class, 'index'])->middleware('api.ability:incidents:read');
        Route::post('attendance-incidents', [AttendanceIncidentController::class, 'store'])->middleware('api.ability:incidents:write');
        Route::get('attendance-incidents/{incidentId}', [AttendanceIncidentController::class, 'show'])->middleware('api.ability:incidents:read');
        Route::post('attendance-incidents/{incidentId}/cancel', [AttendanceIncidentController::class, 'cancel'])->middleware('api.ability:incidents:write');
        Route::get('attendance-periods', [AttendancePeriodController::class, 'index'])->middleware('api.ability:work-days:read');
        Route::get('attendance-periods/{periodId}/payroll-csv', [AttendancePeriodController::class, 'exportPayrollCsv'])->middleware('api.ability:exports:read');
        Route::get('attendance-periods/{periodId}/payroll-xlsx', [AttendancePeriodController::class, 'exportPayrollXlsx'])->middleware('api.ability:exports:read');
        Route::get('attendance-periods/{periodId}', [AttendancePeriodController::class, 'show'])->middleware('api.ability:work-days:read');
        Route::put('workers/{workerId}', [WorkerController::class, 'update'])->middleware('api.ability:workers:write');
        Route::post('time-events', [TimeEventController::class, 'store'])->middleware('api.ability:time-events:write');
        Route::get('time-events', [TimeEventController::class, 'index'])->middleware('api.ability:time-events:read');
        Route::get('time-events/{eventId}', [TimeEventController::class, 'show'])->middleware('api.ability:time-events:read');
        Route::post('time-events/{eventId}/void', [TimeEventController::class, 'void'])->middleware('api.ability:time-events:write');
        Route::post('time-events/{eventId}/approve', [TimeEventController::class, 'approve'])->middleware('api.ability:time-events:write');
        Route::post('time-events/{eventId}/reject', [TimeEventController::class, 'reject'])->middleware('api.ability:time-events:write');
        Route::get('work-days', [WorkDayController::class, 'index'])->middleware('api.ability:work-days:read');
        Route::get('work-days/{workDayId}', [WorkDayController::class, 'show'])->middleware('api.ability:work-days:read');
    });

Route::prefix('v1/time/me')
    ->middleware(['auth:sanctum', 'api.trace', 'api.tenant', 'product:time', 'throttle:api', 'api.ability:self:read'])
    ->group(function (): void {
        Route::get('/', [PersonalTimeController::class, 'context']);
        Route::get('marking-security', [PersonalTimeController::class, 'markingSecurity']);
        Route::get('alerts', [PersonalTimeController::class, 'alerts']);
        Route::post('time-events', [PersonalTimeController::class, 'storeEvent'])->middleware('api.ability:self:write');
        Route::post('time-events/sync', [PersonalTimeController::class, 'syncEvents'])->middleware('api.ability:self:write');
        Route::get('schedule', [PersonalTimeController::class, 'schedule']);
        Route::get('time-events', [PersonalTimeController::class, 'events']);
        Route::get('time-events/{eventId}', [PersonalTimeController::class, 'event']);
        Route::get('work-days', [PersonalTimeController::class, 'workDays']);
        Route::get('work-days/{workDayId}', [PersonalTimeController::class, 'workDay']);
        Route::delete('access-token', [PersonalTimeController::class, 'revokeToken']);
    });
