<?php

namespace App\Domains\Attendance\Actions;

use App\Models\Alert;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class BuildWeeklyComplianceReportAction
{
    public function handle(int $companyId, Collection $selectedDays): array
    {
        $selected = $selectedDays->pluck('id')->flip();
        $weekStart = fn (WorkDay $day): string => $day->work_date->toImmutable()->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
        $weeks = $selectedDays->groupBy($weekStart);
        $summary = collect();
        $detail = collect();
        $markingLinks = [];

        foreach ($weeks as $start => $matches) {
            $end = CarbonImmutable::parse($start)->addDays(6)->toDateString();
            $days = WorkDay::query()->where('company_id', $companyId)
                ->whereIn('worker_id', $matches->pluck('worker_id')->unique())
                ->whereDate('work_date', '>=', $start)->whereDate('work_date', '<=', $end)
                ->with(['worker', 'center', 'activeCalculation', 'alerts' => fn ($alerts) => $alerts
                    ->where('company_id', $companyId)->whereIn('status', Alert::OPEN_STATUSES)])
                ->orderBy('work_date')->orderBy('id')
                ->limit(5001 - $detail->count())->get();
            if ($detail->count() + $days->count() > 5000) {
                throw ValidationException::withMessages(['from' => 'El reporte semanal supera 5,000 jornadas. Reduce el rango o el alcance.']);
            }

            foreach ($days->groupBy('worker_id') as $workerDays) {
                $worker = $workerDays->first()->worker;
                $hasCalculation = fn (WorkDay $day): bool => $day->activeCalculation !== null
                    && $day->activeCalculation->classification !== WorkDayCalculation::CLASSIFICATION_PENDING;
                $calculated = $workerDays->filter($hasCalculation);
                $sum = fn (string $field): ?int => $calculated->isEmpty() ? null : (int) $calculated->sum('activeCalculation.'.$field);
                $pending = $workerDays->count() - $calculated->count();
                $markingLinks[] = ['scope' => 'worker', 'worker' => $workerDays->first()->worker_id, 'from' => $start, 'to' => $end];

                $summary->push([
                    $start, $end, $worker?->employee_code, $worker?->full_name,
                    $sum('ordinary_minutes'), $sum('overtime_double_minutes'), $sum('overtime_triple_minutes'),
                    $calculated->groupBy(fn (WorkDay $day) => $day->work_date->toDateString())
                        ->filter(fn ($dateDays) => $dateDays->sum('activeCalculation.overtime_minutes') > 0)->count(),
                    $workerDays->where('day_type', 'rest')->unique(fn (WorkDay $day) => $day->work_date->toDateString())->count(),
                    $workerDays->sum(fn (WorkDay $day) => $day->alerts->count()), $pending,
                ]);

                foreach ($workerDays as $day) {
                    $calculation = $hasCalculation($day) ? $day->activeCalculation : null;
                    $detail->push([
                        $start, $day->work_date->toDateString(), $worker?->employee_code, $worker?->full_name, $day->center?->name,
                        $selected->has($day->id) ? 'Seleccionada' : 'Contexto semanal',
                        $day->day_type === 'rest' ? 'Descanso programado' : match ($calculation?->classification) {
                            'diurnal' => 'Diurna', 'nocturnal' => 'Nocturna', 'mixed' => 'Mixta', default => 'Sin clasificar',
                        },
                        $calculation?->total_work_minutes, $calculation?->ordinary_minutes,
                        $calculation?->overtime_double_minutes, $calculation?->overtime_triple_minutes,
                        $day->alerts->pluck('title')->unique()->implode(' · '),
                    ]);
                }
            }
        }

        return [
            'columns' => ['Semana desde', 'Semana hasta', 'Clave', 'Trabajador', 'Ordinarios (min)', 'Extra doble (min)', 'Extra triple (min)', 'Días con extra', 'Descansos programados', 'Avisos abiertos', 'Jornadas sin clasificar'],
            'rows' => $summary,
            'markingLinks' => $markingLinks,
            'eventColumns' => ['Inicio de semana', 'Fecha', 'Clave', 'Trabajador', 'Centro', 'Alcance', 'Jornada', 'Trabajados (min)', 'Ordinarios (min)', 'Extra doble (min)', 'Extra triple (min)', 'Avisos abiertos'],
            'eventRows' => $detail,
        ];
    }
}
