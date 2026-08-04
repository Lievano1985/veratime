<?php

namespace App\Domains\WorkDays\Actions;

use App\Models\Center;
use App\Models\Company;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class RefreshWorkDaysForDateRangeAction
{
    public function __construct(
        private readonly GenerateWorkDaysFromPublishedSchedulesAction $scheduledWorkDays,
        private readonly GenerateUnscheduledWorkDaysFromTimeEventsAction $unscheduledWorkDays,
    ) {}

    /**
     * @return array{scheduled: int, unscheduled: int, total: int}
     */
    public function handle(Company $company, string $startDate, string $endDate, ?Center $center = null): array
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('La generacion de jornadas requiere una empresa activa.');
        }

        if ($center && $center->company_id !== $company->id) {
            throw new InvalidArgumentException('El centro debe pertenecer a la empresa activa.');
        }

        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();

        if ($end->lt($start)) {
            throw new InvalidArgumentException('El rango de jornadas no es valido.');
        }

        $scheduled = $this->scheduledWorkDays->handle($company, $start->toDateString(), $end->toDateString(), $center);
        $unscheduled = $this->unscheduledWorkDays->handle($company, $start->toDateString(), $end->toDateString(), $center);

        return [
            'scheduled' => $scheduled,
            'unscheduled' => $unscheduled,
            'total' => $scheduled + $unscheduled,
        ];
    }
}
