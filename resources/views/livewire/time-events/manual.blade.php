<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\TimeRecords\Actions\ApproveManualTimeEventAction;
use App\Domains\TimeRecords\Actions\RegisterManualTimeEventAction;
use App\Domains\TimeRecords\Actions\RejectManualTimeEventAction;
use App\Domains\TimeRecords\Actions\VoidTimeEventAction;
use App\Domains\WorkDays\Actions\RefreshWorkDaysForDateRangeAction;
use App\Models\TimeEvent;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public string $workerId = '';

    public string $eventType = 'clock_in';

    public string $occurredLocalDate = '';

    public string $occurredLocalTime = '';

    public string $reason = '';

    public ?int $voidingEventId = null;

    public string $voidReason = '';

    public ?int $rejectingEventId = null;

    public string $rejectReason = '';

    public string $sourceFilter = '';

    public string $statusFilter = '';

    public function mount(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [TimeEvent::class, $company]);

        $this->workerId = (string) ($company->workers()
            ->where('status', 'active')
            ->orderBy('full_name')
            ->value('id') ?? '');

        $now = CarbonImmutable::now($company->timezone);
        $this->occurredLocalDate = $now->toDateString();
        $this->occurredLocalTime = $now->format('H:i');
    }

    public function capture(CurrentCompany $currentCompany, RegisterManualTimeEventAction $action): void
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
            'eventType' => ['required', Rule::in(['clock_in', 'clock_out', 'break_start', 'break_end'])],
            'occurredLocalDate' => ['required', 'date'],
            'occurredLocalTime' => ['required', 'date_format:H:i'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $worker = Worker::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->findOrFail((int) $validated['workerId']);

        try {
            $event = $action->handle($company, auth()->user(), $worker, [
                'event_type' => $validated['eventType'],
                'occurred_local_date' => $validated['occurredLocalDate'],
                'occurred_local_time' => $validated['occurredLocalTime'],
                'reason' => $validated['reason'],
            ]);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'workerId' => $exception->getMessage(),
            ]);
        }

        $this->reason = '';
        $this->resetPage();
        Session::flash('status', 'Captura manual guardada para revision.');
    }

    public function updatedSourceFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function startVoid(int $eventId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        $event = TimeEvent::query()
            ->where('company_id', $company->id)
            ->findOrFail($eventId);

        Gate::authorize('void', $event);

        $this->voidingEventId = $event->id;
        $this->voidReason = '';
    }

    public function cancelVoid(): void
    {
        $this->voidingEventId = null;
        $this->voidReason = '';
    }

    public function approveManualEvent(int $eventId, CurrentCompany $currentCompany, ApproveManualTimeEventAction $action, RefreshWorkDaysForDateRangeAction $refreshWorkDays): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        $event = TimeEvent::query()
            ->where('company_id', $company->id)
            ->findOrFail($eventId);

        try {
            $approved = $action->handle($event, auth()->user());

            if ($approved->employment_relationship_id) {
                $refreshWorkDays->handle(
                    $company,
                    $approved->occurred_local_date->toDateString(),
                    $approved->occurred_local_date->toDateString(),
                    $approved->center,
                );
            }
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'statusFilter' => $exception->getMessage(),
            ]);
        }

        $this->resetPage();
        Session::flash('status', 'Captura manual aprobada y jornadas actualizadas.');
    }

    public function startReject(int $eventId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        $event = TimeEvent::query()
            ->where('company_id', $company->id)
            ->findOrFail($eventId);

        Gate::authorize('reject', $event);

        $this->rejectingEventId = $event->id;
        $this->rejectReason = '';
    }

    public function cancelReject(): void
    {
        $this->rejectingEventId = null;
        $this->rejectReason = '';
    }

    public function rejectManualEvent(CurrentCompany $currentCompany, RejectManualTimeEventAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        $validated = $this->validate([
            'rejectingEventId' => [
                'required',
                'integer',
                Rule::exists('time_events', 'id')->where('company_id', $company->id),
            ],
            'rejectReason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $event = TimeEvent::query()
            ->where('company_id', $company->id)
            ->findOrFail((int) $validated['rejectingEventId']);

        try {
            $action->handle($event, auth()->user(), $validated['rejectReason']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'rejectReason' => $exception->getMessage(),
            ]);
        }

        $this->cancelReject();
        $this->resetPage();
        Session::flash('status', 'Captura manual rechazada.');
    }

    public function voidEvent(CurrentCompany $currentCompany, VoidTimeEventAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        $validated = $this->validate([
            'voidingEventId' => [
                'required',
                'integer',
                Rule::exists('time_events', 'id')->where('company_id', $company->id),
            ],
            'voidReason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $event = TimeEvent::query()
            ->where('company_id', $company->id)
            ->findOrFail((int) $validated['voidingEventId']);

        try {
            $action->handle($event, auth()->user(), $validated['voidReason']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'voidReason' => $exception->getMessage(),
            ]);
        }

        $this->cancelVoid();
        $this->cancelReject();
        $this->resetPage();
        Session::flash('status', 'Evento de jornada anulado.');
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [TimeEvent::class, $company]);

        $workers = $company->workers()
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get();

        $events = $company->timeEvents()
            ->with(['worker', 'sourceUser', 'voidedBy'])
            ->when($this->sourceFilter !== '', fn ($query) => $query->where('source', $this->sourceFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->latest('occurred_at_utc')
            ->paginate(10);

        return [
            'company' => $company,
            'workers' => $workers,
            'events' => $events,
            'eventSources' => TimeEvent::SOURCES,
            'eventStatuses' => TimeEvent::STATUSES,
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
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

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'valid' => 'Valido',
            'pending_review' => 'En revision',
            'voided' => 'Anulado',
            'replaced' => 'Reemplazado',
            'ignored' => 'Ignorado',
            default => ucfirst($status),
        };
    }

    private function statusVariant(string $status): string
    {
        return match ($status) {
            'valid' => 'success',
            'pending_review' => 'warning',
            'voided' => 'danger',
            default => 'neutral',
        };
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div>
        <flux:heading size="xl">Captura manual</flux:heading>
        <flux:subheading>Registra eventos justificados sin calcular jornadas.</flux:subheading>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="capture" class="grid gap-4 rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/60 lg:grid-cols-2">
        <flux:select label="Trabajador" wire:model="workerId">
            <flux:select.option value="">Selecciona</flux:select.option>
            @foreach ($workers as $worker)
                <flux:select.option value="{{ $worker->id }}">{{ $worker->full_name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select label="Tipo de evento" wire:model="eventType">
            <flux:select.option value="clock_in">Entrada</flux:select.option>
            <flux:select.option value="clock_out">Salida</flux:select.option>
            <flux:select.option value="break_start">Inicio de pausa</flux:select.option>
            <flux:select.option value="break_end">Fin de pausa</flux:select.option>
        </flux:select>

        <flux:input label="Fecha" type="date" wire:model="occurredLocalDate" />
        <flux:input label="Hora" type="time" wire:model="occurredLocalTime" />

        <div class="lg:col-span-2">
            <flux:textarea label="Motivo" wire:model="reason" rows="4" placeholder="Describe por que se captura manualmente este evento." />
        </div>

        <div class="lg:col-span-2">
            <flux:button type="submit" variant="primary">Guardar captura manual</flux:button>
        </div>
    </form>

    <section class="space-y-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <flux:heading>Eventos de jornada</flux:heading>

            <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[420px]">
                <flux:select label="Fuente" wire:model.live="sourceFilter">
                    <flux:select.option value="">Todas</flux:select.option>
                    @foreach ($eventSources as $source)
                        <flux:select.option value="{{ $source }}">{{ $source }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select label="Estado" wire:model.live="statusFilter">
                    <flux:select.option value="">Todos</flux:select.option>
                    @foreach ($eventStatuses as $status)
                        <flux:select.option value="{{ $status }}">{{ $this->statusLabel($status) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

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
                        <th class="px-4 py-3">Revision</th>
                        <th class="px-4 py-3">Anulacion</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
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
                                <x-ui.badge variant="{{ $this->statusVariant($event->status) }}">
                                    {{ $this->statusLabel($event->status) }}
                                </x-ui.badge>
                            </td>
                            <td class="px-4 py-3">
                                @if ($event->metadata['review'] ?? null)
                                    <div class="space-y-1">
                                        <p class="font-medium">{{ $event->metadata['review']['decision'] === 'approved' ? 'Aprobada' : 'Rechazada' }}</p>
                                        <p class="text-xs text-zinc-500">{{ $event->metadata['review']['reviewed_at'] ?? '' }}</p>
                                    </div>
                                @elseif ($event->source === 'admin_manual' && $event->status === 'pending_review')
                                    <x-ui.badge variant="warning">Pendiente</x-ui.badge>
                                @else
                                    <span class="text-zinc-500">No aplica</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($event->voided_at)
                                    <div class="space-y-1">
                                        <p class="font-medium">{{ $event->voided_at->utc()->format('Y-m-d H:i') }} UTC</p>
                                        <p class="text-xs text-zinc-500">{{ $event->voidedBy?->name ?? 'Usuario no disponible' }}</p>
                                    </div>
                                @else
                                    <span class="text-zinc-500">No aplica</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($event->source === 'admin_manual' && $event->status === 'pending_review')
                                    <div class="flex justify-end gap-2">
                                        <flux:button type="button" size="xs" variant="primary" wire:click="approveManualEvent({{ $event->id }})">Aprobar</flux:button>
                                        <flux:button type="button" size="xs" variant="ghost" wire:click="startReject({{ $event->id }})">Rechazar</flux:button>
                                    </div>
                                @elseif ($event->status !== 'voided')
                                    <flux:button type="button" size="xs" variant="ghost" wire:click="startVoid({{ $event->id }})">Anular</flux:button>
                                @else
                                    <span class="text-xs text-zinc-500">Sin acciones</span>
                                @endif
                            </td>
                        </tr>
                        @if ($rejectingEventId === $event->id)
                            <tr>
                                <td colspan="9" class="bg-red-50 px-4 py-4 dark:bg-red-950/30">
                                    <form wire:submit="rejectManualEvent" class="grid gap-3 lg:grid-cols-[1fr_auto] lg:items-end">
                                        <flux:textarea label="Motivo de rechazo" wire:model="rejectReason" rows="2" />
                                        <div class="flex gap-2">
                                            <flux:button type="submit" variant="primary">Confirmar rechazo</flux:button>
                                            <flux:button type="button" variant="ghost" wire:click="cancelReject">Cancelar</flux:button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                        @if ($voidingEventId === $event->id)
                            <tr>
                                <td colspan="9" class="bg-amber-50 px-4 py-4 dark:bg-amber-950/30">
                                    <form wire:submit="voidEvent" class="grid gap-3 lg:grid-cols-[1fr_auto] lg:items-end">
                                        <flux:textarea label="Motivo de anulacion" wire:model="voidReason" rows="2" />
                                        <div class="flex gap-2">
                                            <flux:button type="submit" variant="primary">Confirmar anulacion</flux:button>
                                            <flux:button type="button" variant="ghost" wire:click="cancelVoid">Cancelar</flux:button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-zinc-500">Sin eventos de jornada.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $events->links() }}
    </section>
</section>
