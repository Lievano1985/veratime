<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\TimeRecords\Actions\RegisterManualTimeEventAction;
use App\Models\TimeEvent;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $workerId = '';

    public string $eventType = 'clock_in';

    public string $occurredLocalDate = '';

    public string $occurredLocalTime = '';

    public string $reason = '';

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
        Session::flash('status', 'Captura manual guardada para revision.');
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
            ->with('worker')
            ->where('source', 'admin_manual')
            ->latest('occurred_at_utc')
            ->limit(10)
            ->get();

        return [
            'company' => $company,
            'workers' => $workers,
            'events' => $events,
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

    <form wire:submit="capture" class="grid gap-4 rounded-md border border-zinc-200 p-4 dark:border-zinc-700 lg:grid-cols-2">
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
        <flux:heading>Ultimas capturas manuales</flux:heading>

        <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
            <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Trabajador</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Hora local</th>
                        <th class="px-4 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($events as $event)
                        <tr>
                            <td class="px-4 py-3">{{ $event->occurred_local_date?->toDateString() }}</td>
                            <td class="px-4 py-3">{{ $event->worker->full_name }}</td>
                            <td class="px-4 py-3">{{ $this->eventLabel($event->event_type) }}</td>
                            <td class="px-4 py-3">{{ $event->occurred_local_time }}</td>
                            <td class="px-4 py-3">{{ $event->status }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-500">Sin capturas manuales.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</section>