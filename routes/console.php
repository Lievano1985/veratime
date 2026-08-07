<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Domains\WorkDays\Actions\ProcessCompanyWorkDaysAction;
use App\Domains\WorkDays\Actions\RunDueWorkDaysAutoRefreshAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\WorkDayCalculation;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('work-days:refresh {--company=} {--from=} {--to=} {--center=} {--reason=}', function (ProcessCompanyWorkDaysAction $action): int {
    $companyId = $this->option('company');
    $reason = trim((string) $this->option('reason'));

    if (! $companyId) {
        $this->error('La opcion --company es requerida.');

        return 1;
    }

    if ($reason === '') {
        $this->error('La opcion --reason es requerida para reproceso manual.');

        return 1;
    }

    $company = Company::query()->with('setting')->find($companyId);

    if (! $company) {
        $this->error('Empresa no encontrada.');

        return 1;
    }

    $range = $this->option('from') && $this->option('to')
        ? ['start_date' => (string) $this->option('from'), 'end_date' => (string) $this->option('to')]
        : $action->defaultAvailableRange($company);

    $center = null;
    if ($centerId = $this->option('center')) {
        $center = Center::query()
            ->where('company_id', $company->id)
            ->find($centerId);

        if (! $center) {
            $this->error('Centro no encontrado para la empresa indicada.');

            return 1;
        }
    }

    $result = $action->handle(
        $company,
        $range['start_date'],
        $range['end_date'],
        $center,
        mode: 'manual_command',
        generatedByType: WorkDayCalculation::GENERATED_BY_USER,
        reason: $reason,
    );

    $this->info("Jornadas procesadas: {$result['total']} actualizadas, {$result['calculated']} calculadas, {$result['under_review']} en revision, {$result['skipped']} sin eventos validos, {$result['alerts_created_or_updated']} alertas revisadas.");

    return 0;
})->purpose('Refresh and calculate work_days for one company and date range.');

Artisan::command('work-days:auto-refresh', function (RunDueWorkDaysAutoRefreshAction $action): int {
    $results = $action->handle();

    $this->info('Empresas procesadas: '.$results->count());

    foreach ($results as $result) {
        $this->line("Empresa {$result['company_id']}: {$result['total']} actualizadas, {$result['calculated']} calculadas, {$result['alerts_created_or_updated']} alertas ({$result['start_date']} a {$result['end_date']}).");
    }

    return 0;
})->purpose('Refresh due work_days based on company configured local time.');

Schedule::command('work-days:auto-refresh')->everyMinute()->withoutOverlapping();
Schedule::command('queue:work database --queue=work-days,default --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
