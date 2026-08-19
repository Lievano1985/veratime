<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Domains\TimeRecords\Actions\RegisterWebTimeEventAction;
use App\Domains\TimeRecords\Actions\ResolveCurrentTimeRecordStateAction;
use App\Models\TimeEvent;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $workerId = '';

    public function mount(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [TimeEvent::class, $company]);

        $visibleCenterIds = $this->visibleCenterIds($company);

        $this->workerId = (string) ($company->workers()
            ->where('status', 'active')
            ->when($visibleCenterIds !== null, function ($query) use ($visibleCenterIds): void {
                $query->whereHas('activeEmploymentRelationship', fn ($relationshipQuery) => $relationshipQuery->whereIn('center_id', $visibleCenterIds));
            })
            ->orderBy('full_name')
            ->value('id') ?? '');
    }

    public function record(string $eventType, CurrentCompany $currentCompany, RegisterWebTimeEventAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [TimeEvent::class, $company]);

        $validated = $this->validate([
            'workerId' => [
                'required',
                'integer',
                Rule::exists('workers', 'id')
                    ->where('company_id', $company->id)
                    ->where('status', 'active'),
            ],
        ]);

        $worker = Worker::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->findOrFail((int) $validated['workerId']);
        $this->assertWorkerWithinVisibleCenters($company, $worker);

        try {
            $event = $action->handle($company, auth()->user(), $worker, $eventType);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'workerId' => $exception->getMessage(),
            ]);
        }

        Session::flash('status', $this->eventMessage($event->event_type));
    }

    public function with(CurrentCompany $currentCompany, ResolveCurrentTimeRecordStateAction $resolveState): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [TimeEvent::class, $company]);

        $visibleCenterIds = $this->visibleCenterIds($company);

        $workers = $company->workers()
            ->where('status', 'active')
            ->when($visibleCenterIds !== null, function ($query) use ($visibleCenterIds): void {
                $query->whereHas('activeEmploymentRelationship', fn ($relationshipQuery) => $relationshipQuery->whereIn('center_id', $visibleCenterIds));
            })
            ->orderBy('full_name')
            ->get();

        $worker = filled($this->workerId)
            ? $workers->firstWhere('id', (int) $this->workerId)
            : null;

        $relationship = $worker?->activeEmploymentRelationship()->with('center')->first();
        $center = $relationship?->center;
        $state = $worker ? $resolveState->handle($company, $worker, null, $center) : null;

        $events = $worker && $state
            ? $company->timeEvents()
                ->where('worker_id', $worker->id)
                ->where(function ($query) use ($state): void {
                    $query->whereDate('occurred_local_date', $state['local_date'])
                        ->orWhere('occurred_local_date', 'like', $state['local_date'].'%');
                })
                ->where('status', 'valid')
                ->orderBy('occurred_at_utc')
                ->get()
            : collect();

        return [
            'company' => $company,
            'workers' => $workers,
            'selectedWorker' => $worker,
            'state' => $state,
            'events' => $events,
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function visibleCenterIds($company): ?array
    {
        if (in_array(auth()->user()->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return null;
        }

        if (! in_array(auth()->user()->roleKeyForCompany($company), RoleKey::scopedOperators(), true)) {
            return [];
        }

        return app(ScopedOperationalAccess::class)->scope(auth()->user(), $company)['center_ids'];
    }

    private function assertWorkerWithinVisibleCenters($company, Worker $worker): void
    {
        $visibleCenterIds = $this->visibleCenterIds($company);

        if ($visibleCenterIds === null) {
            return;
        }

        $centerId = $worker->activeEmploymentRelationship()->value('center_id');

        if (! $centerId || ! in_array($centerId, $visibleCenterIds, true)) {
            throw ValidationException::withMessages([
                'workerId' => 'No puedes capturar eventos para trabajadores fuera de tu centro asignado.',
            ]);
        }
    }

    private function eventMessage(string $eventType): string
    {
        return match ($eventType) {
            'clock_in' => 'Entrada registrada.',
            'clock_out' => 'Salida registrada.',
            'break_start' => 'Inicio de pausa registrado.',
            'break_end' => 'Fin de pausa registrado.',
            default => 'Registro guardado.',
        };
    }

    private function stateLabel(?string $state): string
    {
        return match ($state) {
            'trabajando' => 'Trabajando',
            'en_pausa' => 'En pausa',
            'jornada_cerrada' => 'Jornada cerrada',
            default => 'Sin entrada',
        };
    }

    private function eventLabel(string $eventType): string
    {
        return match ($eventType) {
            'clock_in' => 'Entrada',
            'clock_out' => 'Salida',
            'break_start' => 'Inicio de pausa',
            'break_end' => 'Fin de pausa',
            default => $eventType,
        };
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Registro de jornada</flux:heading>
            <flux:subheading>Registra eventos web basicos sin calcular jornadas.</flux:subheading>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <section class="grid gap-4 lg:grid-cols-[1fr_1fr]">
        <div class="space-y-4 rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
            <div>
                <flux:heading>Empresa activa</flux:heading>
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $company->name }}</p>
            </div>

            <flux:select label="Trabajador" wire:model.live="workerId">
                <flux:select.option value="">Selecciona</flux:select.option>
                @foreach ($workers as $worker)
                    <flux:select.option value="{{ $worker->id }}">{{ $worker->full_name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($state)
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <p class="text-xs uppercase text-zinc-500">Estado</p>
                        <p class="font-medium">{{ $this->stateLabel($state['state']) }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-zinc-500">Fecha local</p>
                        <p class="font-medium">{{ $state['local_date'] }}</p>
                    </div>
                    <div>
                        <p class="text-xs uppercase text-zinc-500">Zona horaria</p>
                        <p class="font-medium">{{ $state['timezone'] }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if (in_array('clock_in', $state['allowed_actions'], true))
                        <flux:button type="button" variant="primary" wire:click="record('clock_in')">Registrar entrada</flux:button>
                    @endif
                    @if (in_array('break_start', $state['allowed_actions'], true))
                        <flux:button type="button" variant="primary" wire:click="record('break_start')">Iniciar pausa</flux:button>
                    @endif
                    @if (in_array('break_end', $state['allowed_actions'], true))
                        <flux:button type="button" variant="primary" wire:click="record('break_end')">Terminar pausa</flux:button>
                    @endif
                    @if (in_array('clock_out', $state['allowed_actions'], true))
                        <flux:button type="button" variant="primary" wire:click="record('clock_out')">Registrar salida</flux:button>
                    @endif
                    @if (empty($state['allowed_actions']))
                        <span class="text-sm text-zinc-500">No hay acciones disponibles para el estado actual.</span>
                    @endif
                </div>
            @else
                <p class="text-sm text-zinc-500">No hay trabajadores activos disponibles.</p>
            @endif
        </div>

        <div class="space-y-4 rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
            <flux:heading>Ultimo evento</flux:heading>

            @if ($state && $state['last_event'])
                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs uppercase text-zinc-500">Tipo</dt>
                        <dd class="font-medium">{{ $this->eventLabel($state['last_event']->event_type) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-zinc-500">Hora local</dt>
                        <dd class="font-medium">{{ $state['last_event']->occurred_local_time }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-zinc-500">Fuente</dt>
                        <dd class="font-medium">{{ $state['last_event']->source }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-zinc-500">Estado</dt>
                        <dd class="font-medium">{{ $state['last_event']->status }}</dd>
                    </div>
                </dl>
            @else
                <p class="text-sm text-zinc-500">Sin eventos registrados hoy.</p>
            @endif
        </div>
    </section>

    <section class="space-y-4">
        <flux:heading>Eventos del dia</flux:heading>

        <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
            <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Trabajador</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Hora local</th>
                        <th class="px-4 py-3">Fuente</th>
                        <th class="px-4 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                    @forelse ($events as $event)
                        <tr>
                            <td class="px-4 py-3">{{ $event->occurred_local_date?->toDateString() }}</td>
                            <td class="px-4 py-3">{{ $event->worker->full_name }}</td>
                            <td class="px-4 py-3">{{ $this->eventLabel($event->event_type) }}</td>
                            <td class="px-4 py-3">{{ $event->occurred_local_time }}</td>
                            <td class="px-4 py-3">{{ $event->source }}</td>
                            <td class="px-4 py-3">
                                <x-ui.badge variant="{{ $event->status === 'valid' ? 'success' : ($event->status === 'pending_review' ? 'warning' : ($event->status === 'voided' ? 'danger' : 'neutral')) }}">
                                    {{ $event->status === 'valid' ? 'Valido' : ($event->status === 'pending_review' ? 'En revision' : ($event->status === 'voided' ? 'Anulado' : ucfirst($event->status))) }}
                                </x-ui.badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                                No hay eventos registrados para el dia.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>
