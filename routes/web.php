<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Volt::route('kiosk', 'kiosk.index')->name('kiosk.index');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified', 'current.company'])
    ->name('dashboard');

Route::middleware(['auth', 'current.company'])->group(function () {
    Volt::route('companies', 'companies.index')->name('companies.index');
    Volt::route('centers', 'centers.index')->name('centers.index');
    Volt::route('workers', 'workers.index')->name('workers.index');
    Volt::route('schedules', 'schedules.index')->name('schedules.index');
    Volt::route('scheduling/shifts', 'scheduling.shifts')->name('scheduling.shifts');
    Volt::route('schedule-assignments', 'schedule-assignments.index')->name('schedule-assignments.index');
    Volt::route('mandatory-rest-days', 'mandatory-rest-days.index')->name('mandatory-rest-days.index');
    Volt::route('organization/units', 'organization.units')->name('organization.units');
    Volt::route('organization/assignments', 'organization.assignments')->name('organization.assignments');
    Volt::route('organization/scopes', 'organization.scopes')->name('organization.scopes');
    Volt::route('organization/my-scope', 'organization.my-scope')->name('organization.my-scope');
    Volt::route('time-clock', 'time-clock.index')->name('time-clock.index');
    Volt::route('time-events/manual', 'time-events.manual')->name('time-events.manual');

    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
