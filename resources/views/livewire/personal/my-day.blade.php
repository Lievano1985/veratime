<?php

use Carbon\CarbonImmutable;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Scheduling\Actions\ListPersonalScheduleAction;
use App\Domains\Workers\Actions\ResolvePersonalWorkerAction;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\TimeEvent;
use App\Models\WorkDay;
use Livewire\Volt\Component;

new class extends Component {
    public function with(CurrentCompany $currentCompany, ResolvePersonalWorkerAction $resolve, ListPersonalScheduleAction $listSchedule): array
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);
        $worker = $resolve->handle(auth()->user(), $company);
        $calendarStart = CarbonImmutable::now($company->timezone)->startOfWeek();
        $calendarEnd = $calendarStart->addDays(13);
        $schedule = $listSchedule->handle($company, $worker, [
            'date_from' => $calendarStart->toDateString(),
            'date_to' => $calendarEnd->toDateString(),
        ]);

        return [
            'worker' => $worker,
            'calendarWeeks' => $this->calendarWeeks($schedule, $calendarStart),
            'hasPublishedSchedule' => $schedule->isNotEmpty(),
            'events' => TimeEvent::query()->where('company_id', $company->id)->where('worker_id', $worker->id)->latest('occurred_at_utc')->limit(10)->get(),
            'workDays' => WorkDay::query()->where('company_id', $company->id)->where('worker_id', $worker->id)->latest('work_date')->limit(7)->get(),
        ];
    }

    private function calendarWeeks($schedule, CarbonImmutable $calendarStart): array
    {
        $assignmentsByDate = $schedule->keyBy(fn (DailyScheduleAssignment $assignment) => $assignment->work_date->toDateString());

        return collect(range(0, 1))
            ->map(function (int $weekOffset) use ($assignmentsByDate, $calendarStart): array {
                $weekStart = $calendarStart->addWeeks($weekOffset);

                return [
                    'label' => 'Semana del '.$weekStart->format('d/m'). ' al '.$weekStart->endOfWeek()->format('d/m'),
                    'days' => collect(range(0, 6))
                        ->map(function (int $dayOffset) use ($assignmentsByDate, $weekStart): array {
                            $date = $weekStart->addDays($dayOffset);

                            return [
                                'date' => $date,
                                'assignment' => $assignmentsByDate->get($date->toDateString()),
                            ];
                        })
                        ->all(),
                ];
            })
            ->all();
    }

    public function dayTypeLabel(DailyScheduleAssignment $assignment): string
    {
        return match ($assignment->day_type) {
            'shift' => 'Turno programado',
            'flexible' => 'Jornada flexible',
            'on_call' => 'Disponibilidad',
            'rest' => 'Descanso programado',
            default => 'Sin jornada programada',
        };
    }

    public function segmentRange(DailyScheduleSegment $segment): string
    {
        $start = $this->timeLabel($segment->start_local_time);
        $end = $this->timeLabel($segment->end_local_time);

        if (! $start || ! $end) {
            return 'Horario por confirmar';
        }

        $nextDay = (int) $segment->end_day_offset > (int) $segment->start_day_offset ? ' (+1 dia)' : '';

        return $start.' - '.$end.$nextDay;
    }

    public function requiredMinutesLabel(?int $minutes): ?string
    {
        if (! $minutes) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $remainingMinutes === 0 ? "{$hours} h" : "{$hours} h {$remainingMinutes} min";
    }

    private function timeLabel(?string $time): ?string
    {
        return $time ? substr($time, 0, 5) : null;
    }
}; ?>
<section class="w-full space-y-6 p-6">
    <div><flux:heading size="xl">Mi jornada</flux:heading><flux:subheading>{{ $worker->full_name }}</flux:subheading></div>
    <section class="rounded-lg border p-5">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
            <flux:heading>Mi horario publicado</flux:heading>
            <p class="text-sm text-surface-muted">Esta semana y la siguiente</p>
        </div>

        @if($hasPublishedSchedule)
            <div class="mt-4 space-y-5">
                @foreach($calendarWeeks as $week)
                    <section>
                        <p class="mb-2 text-sm font-medium text-surface-muted">{{ $week['label'] }}</p>
                        <div class="overflow-x-auto pb-1" style="overflow-x: auto;">
                            <div class="gap-3" style="display: grid; grid-template-columns: repeat(7, minmax(130px, 1fr)); gap: 0.75rem; min-width: 980px;">
                                @foreach($week['days'] as $day)
                                    @php($assignment = $day['assignment'])
                                    @php($workSegments = $assignment?->segments->where('segment_type', 'work'))
                                    @php($breakSegments = $assignment?->segments->where('segment_type', 'break'))
                                    <article class="min-h-44 rounded-lg border border-surface-line bg-surface-bg px-3 py-3">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-sm font-semibold text-surface-text">{{ $day['date']->translatedFormat('D') }}</p>
                                            <span class="shrink-0 text-sm text-surface-muted">{{ $day['date']->format('d/m') }}</span>
                                        </div>

                                        @if($assignment)
                                            <p class="mt-3 text-sm font-medium text-surface-text">{{ $this->dayTypeLabel($assignment) }}</p>
                                            @if($assignment->shiftTemplate)
                                                <p class="mt-1 truncate text-xs text-surface-muted" title="{{ $assignment->shiftTemplate->name }}">{{ $assignment->shiftTemplate->name }}</p>
                                            @endif
                                            @if($this->requiredMinutesLabel($assignment->required_minutes))
                                                <p class="mt-2 text-sm font-medium text-brand-navy">{{ $this->requiredMinutesLabel($assignment->required_minutes) }}</p>
                                            @endif

                                            @if($workSegments?->isNotEmpty())
                                                <div class="mt-2 space-y-1 text-xs">
                                                    @foreach($workSegments as $segment)
                                                        <p>{{ $this->segmentRange($segment) }}</p>
                                                    @endforeach
                                                    @foreach($breakSegments as $segment)
                                                        <p class="text-surface-muted">Pausa: {{ $this->segmentRange($segment) }}</p>
                                                    @endforeach
                                                </div>
                                            @elseif($assignment->day_type === 'rest')
                                                <p class="mt-2 text-xs text-surface-muted">Sin jornada.</p>
                                            @elseif($assignment->day_type === 'on_call')
                                                <p class="mt-2 text-xs text-surface-muted">Disponibilidad.</p>
                                            @else
                                                <p class="mt-2 text-xs text-surface-muted">Detalle por confirmar.</p>
                                            @endif
                                        @else
                                            <p class="mt-3 text-xs text-surface-muted">Sin horario publicado.</p>
                                        @endif
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        @else
            <p class="mt-4 rounded-lg border border-dashed border-surface-line px-4 py-5 text-sm text-surface-muted">No tienes horarios publicados para esta semana ni la siguiente.</p>
        @endif
    </section>
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-lg border p-5"><flux:heading>Mis eventos recientes</flux:heading><div class="mt-4 space-y-2">@forelse($events as $event)<p class="text-sm">{{ $event->occurred_at_utc?->setTimezone($event->timezone)->format('d/m H:i') }} · {{ $event->event_type }}</p>@empty<p class="text-sm text-surface-muted">Sin eventos.</p>@endforelse</div></section>
        <section class="rounded-lg border p-5"><flux:heading>Mis jornadas</flux:heading><div class="mt-4 space-y-2">@forelse($workDays as $day)<p class="text-sm">{{ $day->work_date?->format('d/m/Y') }} · {{ $day->status }}</p>@empty<p class="text-sm text-surface-muted">Sin jornadas.</p>@endforelse</div></section>
    </div>
</section>
