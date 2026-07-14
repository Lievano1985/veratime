<?php

namespace App\Domains\Schedules\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\Schedule;
use App\Models\ScheduleAssignment;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssignScheduleToWorkersAction
{
    public function __construct(private readonly ReplaceScheduleAssignmentAction $replaceAction)
    {
    }

    /**
     * @return Collection<int, ScheduleAssignment>
     */
    public function handle(Company $company, Schedule $schedule, array $workerIds, array $data): Collection
    {
        if ($schedule->company_id !== $company->id) {
            throw new InvalidArgumentException('El horario debe pertenecer a la empresa activa.');
        }

        if (blank($data['effective_from'] ?? null)) {
            throw new InvalidArgumentException('La fecha de inicio de la asignacion es requerida.');
        }

        $workerIds = collect($workerIds)
            ->map(fn ($workerId) => (int) $workerId)
            ->filter(fn (int $workerId) => $workerId > 0)
            ->unique()
            ->values();

        if ($workerIds->isEmpty()) {
            throw new InvalidArgumentException('Selecciona al menos una persona trabajadora.');
        }

        $workers = Worker::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereIn('id', $workerIds)
            ->orderBy('full_name')
            ->get();

        if ($workers->count() !== $workerIds->count()) {
            throw new InvalidArgumentException('Todas las personas trabajadoras deben estar activas y pertenecer a la empresa actual.');
        }

        $effectiveFrom = CarbonImmutable::parse($data['effective_from'])->startOfDay();

        return DB::transaction(function () use ($company, $schedule, $workers, $data, $effectiveFrom): Collection {
            return $workers->map(function (Worker $worker) use ($company, $schedule, $data, $effectiveFrom): ScheduleAssignment {
                return $this->replaceAction->handle(
                    $company,
                    $worker,
                    $schedule,
                    $this->resolveEmploymentRelationship($company, $worker, $effectiveFrom),
                    $data,
                );
            })->values();
        });
    }

    private function resolveEmploymentRelationship(Company $company, Worker $worker, CarbonImmutable $effectiveFrom): ?EmploymentRelationship
    {
        return EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $worker->id)
            ->where('status', 'active')
            ->whereDate('started_at', '<=', $effectiveFrom->toDateString())
            ->where(function ($query) use ($effectiveFrom): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $effectiveFrom->toDateString());
            })
            ->latest('started_at')
            ->first();
    }
}