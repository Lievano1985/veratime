<?php

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
        return [
            'worker' => $worker,
            'schedule' => $listSchedule->handle($company, $worker, []),
            'events' => TimeEvent::query()->where('company_id', $company->id)->where('worker_id', $worker->id)->latest('occurred_at_utc')->limit(10)->get(),
            'workDays' => WorkDay::query()->where('company_id', $company->id)->where('worker_id', $worker->id)->latest('work_date')->limit(7)->get(),
        ];
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
            <p class="text-sm text-surface-muted">Pr&oacute;ximos 14 d&iacute;as</p>
        </div>

        <div class="mt-4 space-y-3">
            @forelse($schedule as $assignment)
                @php($workSegments = $assignment->segments->where('segment_type', 'work'))
                @php($breakSegments = $assignment->segments->where('segment_type', 'break'))
                <article class="rounded-lg border border-surface-line bg-surface-bg px-4 py-3">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-semibold text-surface-text">{{ $assignment->work_date?->translatedFormat('l d \d\e F') }}</p>
                            <p class="text-sm text-surface-muted">{{ $this->dayTypeLabel($assignment) }}@if($assignment->shiftTemplate) &middot; {{ $assignment->shiftTemplate->name }}@endif</p>
                        </div>
                        @if($this->requiredMinutesLabel($assignment->required_minutes))
                            <span class="text-sm font-medium text-brand-navy">{{ $this->requiredMinutesLabel($assignment->required_minutes) }}</span>
                        @endif
                    </div>

                    @if($workSegments->isNotEmpty())
                        <div class="mt-3 space-y-1 text-sm">
                            @foreach($workSegments as $segment)
                                <p><span class="text-surface-muted">Turno:</span> {{ $this->segmentRange($segment) }}</p>
                            @endforeach
                            @foreach($breakSegments as $segment)
                                <p><span class="text-surface-muted">Pausa:</span> {{ $this->segmentRange($segment) }}</p>
                            @endforeach
                        </div>
                    @elseif($assignment->day_type === 'rest')
                        <p class="mt-3 text-sm text-surface-muted">No tienes jornada programada este d&iacute;a.</p>
                    @elseif($assignment->day_type === 'on_call')
                        <p class="mt-3 text-sm text-surface-muted">Tienes disponibilidad programada para este d&iacute;a.</p>
                    @else
                        <p class="mt-3 text-sm text-surface-muted">Consulta con tu empresa para conocer los detalles de este horario.</p>
                    @endif
                </article>
            @empty
                <p class="rounded-lg border border-dashed border-surface-line px-4 py-5 text-sm text-surface-muted">No tienes horarios publicados para los pr&oacute;ximos 14 d&iacute;as.</p>
            @endforelse
        </div>
    </section>
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-lg border p-5"><flux:heading>Mis eventos recientes</flux:heading><div class="mt-4 space-y-2">@forelse($events as $event)<p class="text-sm">{{ $event->occurred_at_utc?->setTimezone($event->timezone)->format('d/m H:i') }} · {{ $event->event_type }}</p>@empty<p class="text-sm text-surface-muted">Sin eventos.</p>@endforelse</div></section>
        <section class="rounded-lg border p-5"><flux:heading>Mis jornadas</flux:heading><div class="mt-4 space-y-2">@forelse($workDays as $day)<p class="text-sm">{{ $day->work_date?->format('d/m/Y') }} · {{ $day->status }}</p>@empty<p class="text-sm text-surface-muted">Sin jornadas.</p>@endforelse</div></section>
    </div>
</section>
