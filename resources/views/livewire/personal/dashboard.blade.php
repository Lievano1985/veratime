<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Workers\Actions\BuildPersonalDashboardAction;
use Livewire\Volt\Component;

new class extends Component {
    public function with(CurrentCompany $currentCompany, BuildPersonalDashboardAction $dashboard): array
    {
        $company = $currentCompany->get();
        abort_unless($company, 403);

        return $dashboard->handle(auth()->user(), $company);
    }

    public function minutes(int $value): string
    {
        return intdiv($value, 60).' h'.($value % 60 ? ' '.($value % 60).' min' : '');
    }

    public function eventLabel(string $value): string
    {
        return match ($value) {
            'clock_in' => 'Entrada', 'clock_out' => 'Salida', 'break_start' => 'Inicio de descanso',
            'break_end' => 'Fin de descanso', 'manual_entry' => 'Registro manual', default => 'Marcaje',
        };
    }

    public function sourceLabel(string $value): string
    {
        return match ($value) {
            'pwa' => 'App móvil', 'kiosk' => 'Kiosco', 'web' => 'Web', 'admin_manual' => 'Captura administrativa',
            'api' => 'API', 'csv' => 'Importación', default => 'Sistema',
        };
    }
}; ?>

<section class="space-y-6 p-4 lg:p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-brand-blue">Mi espacio personal</p>
            <flux:heading size="xl">Hola, {{ $worker->full_name }}</flux:heading>
            <p class="mt-1 text-sm text-surface-muted">{{ $today->translatedFormat('l d \d\e F \d\e Y') }} · {{ $relationship?->center?->name ?? 'Centro por asignar' }}</p>
        </div>
        <a href="{{ route('personal.my-day') }}" wire:navigate class="btn-outline cursor-pointer">Ver mi calendario</a>
    </div>

    <section class="overflow-hidden rounded-2xl border border-blue-200 bg-white shadow-sm">
        <div class="bg-blue-50 p-5"><x-ui.panel-heading title="Mi día de hoy" description="Tu horario publicado y el último marcaje válido del día." /></div>
        <div class="grid gap-6 p-5 md:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-surface-muted">Horario de hoy</p>
                @if ($todaySchedule)
                    <p class="mt-2 text-lg font-bold text-brand-navy">{{ match ($todaySchedule->day_type) { 'rest' => 'Descanso programado', 'flexible' => 'Jornada flexible', 'on_call' => 'Disponibilidad', 'shift' => $todaySchedule->shiftTemplate?->name ?? 'Horario asignado', default => 'Sin jornada definida' } }}</p>
                    @foreach ($todaySchedule->segments as $segment)
                        <p class="mt-2 text-sm text-surface-text">
                            {{ $segment->segment_type === 'break' ? 'Descanso' : 'Trabajo' }}:
                            @if ($segment->start_local_time && $segment->end_local_time)
                                {{ substr($segment->start_local_time, 0, 5) }}{{ $segment->start_day_offset ? ' (+1 día)' : '' }}–{{ substr($segment->end_local_time, 0, 5) }}{{ $segment->end_day_offset ? ' (+1 día)' : '' }}
                            @else
                                {{ $segment->duration_minutes ?? 0 }} min
                            @endif
                        </p>
                    @endforeach
                @else
                    <p class="mt-2 font-semibold text-brand-navy">Sin horario publicado para hoy</p>
                    <p class="mt-2 text-sm text-surface-muted">Consulta con RH si esperabas tener un turno asignado.</p>
                @endif
            </div>
            <div class="rounded-xl border border-surface-line bg-surface-bg p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-surface-muted">Último marcaje válido de hoy</p>
                @if ($todayEvent)
                    <p class="mt-2 text-2xl font-bold text-brand-navy">{{ $todayEvent->occurred_at_utc->setTimezone($todayEvent->timezone)->format('H:i') }}</p>
                    <p class="mt-1 text-sm">{{ $this->eventLabel($todayEvent->event_type) }} · {{ $this->sourceLabel($todayEvent->source) }}</p>
                @else
                    <p class="mt-3 text-sm text-surface-muted">Todavía no hay marcajes válidos de hoy.</p>
                @endif
                <p class="mt-3 text-xs text-surface-muted">Zona horaria: {{ $today->timezoneName }}</p>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-surface-line bg-white p-5">
        <x-ui.panel-heading title="Mi semana" description="Del {{ $weekStart->format('d/m/Y') }} al {{ $weekEnd->format('d/m/Y') }}" tone="indigo" />
        <div class="mt-5 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-blue-100 bg-blue-50 p-4"><p class="text-sm text-surface-muted">Horas programadas</p><p class="mt-2 text-2xl font-bold text-brand-navy">{{ $this->minutes($plannedMinutes) }}</p><p class="mt-1 text-xs text-surface-muted">Semana completa publicada</p></div>
            <div class="rounded-xl border border-surface-line bg-surface-bg p-4"><p class="text-sm text-surface-muted">Horas calculadas</p><p class="mt-2 text-2xl font-bold text-brand-navy">{{ $this->minutes($calculatedMinutes) }}</p><p class="mt-1 text-xs text-surface-muted">Acumuladas hasta hoy · Provisionales</p></div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4"><p class="text-sm text-surface-muted">Jornadas por revisar</p><p class="mt-2 text-2xl font-bold text-brand-navy">{{ $pendingDays }}</p><p class="mt-1 text-xs text-surface-muted">Pendientes o con alertas esta semana</p></div>
        </div>
        <p class="mt-4 text-xs leading-relaxed text-surface-muted">Las horas calculadas pueden cambiar por revisión o corrección. Este resumen no confirma horas pagadas ni sustituye un periodo cerrado.</p>
    </section>

    <livewire:personal.kiosk-credential />

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-surface-line bg-white p-5">
            <x-ui.panel-heading title="Mis marcajes recientes" tone="blue" />
            <div class="mt-3 divide-y divide-surface-line">
                @forelse ($events as $event)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div><p class="text-sm font-semibold text-brand-navy">{{ $this->eventLabel($event->event_type) }}</p><p class="mt-1 text-xs text-surface-muted">{{ $event->occurred_at_utc->setTimezone($event->timezone)->format('d/m/Y H:i') }} · {{ $this->sourceLabel($event->source) }}</p></div>
                        <x-ui.badge :variant="$event->status === 'pending_review' ? 'warning' : 'success'">{{ $event->status === 'pending_review' ? 'Por revisar' : 'Válido' }}</x-ui.badge>
                    </div>
                @empty
                    <p class="py-4 text-sm text-surface-muted">Aún no tienes marcajes registrados.</p>
                @endforelse
            </div>
        </section>
        <section class="rounded-2xl border border-surface-line bg-white p-5">
            <x-ui.panel-heading title="Mis avisos pendientes" tone="emerald" />
            <div class="mt-4 space-y-3">
                @forelse ($alerts as $alert)
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3"><p class="text-sm font-semibold text-brand-navy">{{ $alert->title }}</p><p class="mt-1 text-xs text-surface-muted">{{ $alert->detected_at?->setTimezone($today->timezoneName)->format('d/m/Y') }} · {{ $alert->status === 'in_review' ? 'En revisión' : 'Pendiente de revisión' }}</p></div>
                @empty
                    <p class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">No tienes avisos pendientes registrados.</p>
                @endforelse
                <p class="text-xs leading-relaxed text-surface-muted">Si falta un marcaje o necesitas una aclaración, comunícate con RH. Los registros se revisan conservando su historial.</p>
                <a href="{{ route('settings.password') }}" wire:navigate class="btn-outline cursor-pointer">Cambiar mi contraseña</a>
            </div>
        </section>
    </div>
</section>
