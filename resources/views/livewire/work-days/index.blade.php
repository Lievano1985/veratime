<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\WorkDays\Actions\ListWorkDaysAction;
use App\Domains\WorkDays\Actions\RunCompanyWorkDaysRefreshAction;
use App\Models\WorkDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    #[Url(as: 'center')]
    public string $centerId = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'schedule')]
    public string $scheduleStatusFilter = '';

    #[Url]
    public string $search = '';

    public array $refreshForm = [];

    public bool $showRefreshPanel = false;

    public function mount(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $today = CarbonImmutable::now($company->setting?->default_timezone ?: $company->timezone)
            ->startOfWeek(\Carbon\CarbonInterface::MONDAY);

        $this->dateFrom = $this->dateFrom !== '' ? $this->dateFrom : $today->toDateString();
        $this->dateTo = $this->dateTo !== '' ? $this->dateTo : $today->addDays(6)->toDateString();
        $this->loadRefreshForm($company);
    }

    public function openRefreshPanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $this->loadRefreshForm($company);
        $this->showRefreshPanel = true;
    }

    public function closeRefreshPanel(): void
    {
        $this->showRefreshPanel = false;
    }

    public function refreshWorkDays(RunCompanyWorkDaysRefreshAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $validated = $this->validate([
            'refreshForm.date_from' => ['required', 'date'],
            'refreshForm.date_to' => ['required', 'date', 'after_or_equal:refreshForm.date_from'],
        ])['refreshForm'];

        $result = $action->handle(
            $company,
            CarbonImmutable::parse($validated['date_from'])->toDateString(),
            CarbonImmutable::parse($validated['date_to'])->toDateString(),
            mode: 'manual_ui',
        );

        $this->dateFrom = CarbonImmutable::parse($validated['date_from'])->toDateString();
        $this->dateTo = CarbonImmutable::parse($validated['date_to'])->toDateString();
        $this->showRefreshPanel = false;
        $this->resetPage();

        Session::flash('status', "Jornadas actualizadas: {$result['total']} total, {$result['scheduled']} programadas y {$result['unscheduled']} no programadas.");
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedCenterId(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedScheduleStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $today = CarbonImmutable::now($company->setting?->default_timezone ?: $company->timezone)
            ->startOfWeek(\Carbon\CarbonInterface::MONDAY);

        $this->dateFrom = $today->toDateString();
        $this->dateTo = $today->addDays(6)->toDateString();
        $this->centerId = '';
        $this->statusFilter = '';
        $this->scheduleStatusFilter = '';
        $this->search = '';
        $this->resetPage();
    }

    public function with(CurrentCompany $currentCompany, ListWorkDaysAction $listWorkDays): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $workDays = $listWorkDays->handle($company, [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'center_id' => $this->centerId === '' ? null : (int) $this->centerId,
            'status' => $this->statusFilter === '' ? null : $this->statusFilter,
            'schedule_status' => $this->scheduleStatusFilter === '' ? null : $this->scheduleStatusFilter,
            'search' => $this->search,
        ]);

        return [
            'company' => $company,
            'centers' => $company->centers()->where('status', 'active')->orderBy('name')->get(),
            'workDays' => $workDays,
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'calculated' => 'Calculada',
            'with_alerts' => 'Con alertas',
            'under_review' => 'En revision',
            'closed' => 'Cerrada',
            default => 'Sin estado',
        };
    }

    private function scheduleStatusLabel(?string $status): string
    {
        return match ($status) {
            'scheduled' => 'Programada',
            'unscheduled' => 'No programada',
            default => 'Sin horario',
        };
    }

    private function dayTypeLabel(?string $dayType): string
    {
        return match ($dayType) {
            'shift' => 'Turno',
            'rest' => 'Descanso',
            'flexible' => 'Flexible',
            'on_call' => 'Guardia',
            'unassigned' => 'Pendiente',
            default => 'Sin tipo',
        };
    }

    private function statusVariant(?string $status): string
    {
        return match ($status) {
            'calculated', 'closed' => 'success',
            'with_alerts' => 'warning',
            'under_review' => 'info',
            default => 'neutral',
        };
    }

    private function scheduleStatusVariant(?string $status): string
    {
        return match ($status) {
            'scheduled' => 'success',
            'unscheduled' => 'warning',
            default => 'neutral',
        };
    }

    private function minutesLabel(?int $minutes): string
    {
        if ($minutes === null) {
            return 'No aplica';
        }

        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        return $remaining === 0 ? "{$hours} h" : "{$hours} h {$remaining} min";
    }

    private function autoRefreshTimeLabel($company): string
    {
        $time = $company->setting?->work_days_auto_refresh_time;

        return $time ? substr((string) $time, 0, 5) : 'Sin configurar';
    }

    private function lastRefreshLabel($company): string
    {
        if (! $company->setting?->work_days_last_refreshed_at) {
            return 'Sin ejecuciones registradas';
        }

        return $company->setting->work_days_last_refreshed_at
            ->timezone($company->setting->default_timezone ?: $company->timezone)
            ->format('Y-m-d H:i');
    }

    private function loadRefreshForm($company): void
    {
        $today = CarbonImmutable::now($company->setting?->default_timezone ?: $company->timezone)
            ->startOfWeek(\Carbon\CarbonInterface::MONDAY);

        $this->refreshForm = [
            'date_from' => $this->dateFrom !== '' ? $this->dateFrom : $today->toDateString(),
            'date_to' => $this->dateTo !== '' ? $this->dateTo : $today->addDays(6)->toDateString(),
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Jornadas</flux:heading>
            <flux:subheading>Consulta jornadas generadas desde horarios publicados y eventos validos.</flux:subheading>
        </div>

        <flux:button type="button" variant="primary" wire:click="openRefreshPanel">
            Actualizar jornadas
        </flux:button>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
        <div class="grid gap-3 lg:grid-cols-[1fr_1fr_1fr_1fr_1fr_auto] lg:items-end">
            <flux:input label="Desde" type="date" wire:model.live="dateFrom" />
            <flux:input label="Hasta" type="date" wire:model.live="dateTo" />

            <flux:select label="Centro" wire:model.live="centerId">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select label="Horario" wire:model.live="scheduleStatusFilter">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="scheduled">Programadas</flux:select.option>
                <flux:select.option value="unscheduled">No programadas</flux:select.option>
            </flux:select>

            <flux:input label="Trabajador" placeholder="Clave o nombre" wire:model.live.debounce.400ms="search" />

            <flux:button type="button" variant="ghost" wire:click="clearFilters">Limpiar</flux:button>
        </div>
    </section>

    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading>Listado operativo</flux:heading>
            <p class="text-sm text-zinc-500">{{ $workDays->total() }} encontradas</p>
        </div>

        <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Trabajador</th>
                            <th class="px-4 py-3">Centro</th>
                            <th class="px-4 py-3">Horario</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Esperado</th>
                            <th class="px-4 py-3">Eventos</th>
                            <th class="px-4 py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                        @forelse ($workDays as $workDay)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3">{{ $workDay->work_date?->toDateString() }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $workDay->worker?->employee_code }} - {{ $workDay->worker?->full_name }}</div>
                                    <div class="text-xs text-zinc-500">{{ $workDay->employmentRelationship?->position_name ?? 'Sin puesto' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $workDay->center?->name ?? 'Sin centro' }}</td>
                                <td class="px-4 py-3">
                                    <x-ui.badge variant="{{ $this->scheduleStatusVariant($workDay->schedule_status) }}">
                                        {{ $this->scheduleStatusLabel($workDay->schedule_status) }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-4 py-3">{{ $this->dayTypeLabel($workDay->day_type) }}</td>
                                <td class="px-4 py-3">{{ $this->minutesLabel($workDay->expected_work_minutes) }}</td>
                                <td class="px-4 py-3">
                                    <span class="font-medium">{{ $workDay->valid_time_event_count }}</span>
                                    @if ($workDay->first_event_at_utc)
                                        <span class="block text-xs text-zinc-500">
                                            {{ $workDay->first_event_at_utc->timezone($workDay->timezone ?: $company->timezone)->format('H:i') }}
                                            -
                                            {{ $workDay->last_event_at_utc?->timezone($workDay->timezone ?: $company->timezone)->format('H:i') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-ui.badge variant="{{ $this->statusVariant($workDay->status) }}">
                                        {{ $this->statusLabel($workDay->status) }}
                                    </x-ui.badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-zinc-500">Sin jornadas en el rango seleccionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $workDays->links() }}
    </section>

    <x-side-panel
        wire:model="showRefreshPanel"
        title="Actualizar jornadas"
        subheading="Ejecuta el refresco manual de jornadas para el rango indicado."
        labelledby="refresh-work-days-title"
    >
        <form wire:submit="refreshWorkDays" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <div class="rounded-md border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800/60">
                    <dl class="grid gap-3">
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Empresa</dt>
                            <dd class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $company->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Zona horaria</dt>
                            <dd class="mt-1 text-zinc-700 dark:text-zinc-200">{{ $company->setting?->default_timezone ?: $company->timezone }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Hora automatica</dt>
                            <dd class="mt-1 text-zinc-700 dark:text-zinc-200">{{ $this->autoRefreshTimeLabel($company) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Ultima ejecucion</dt>
                            <dd class="mt-1 text-zinc-700 dark:text-zinc-200">{{ $this->lastRefreshLabel($company) }}</dd>
                        </div>
                    </dl>
                </div>

                <flux:input wire:model="refreshForm.date_from" label="Desde" type="date" required />
                <flux:input wire:model="refreshForm.date_to" label="Hasta" type="date" required />

                @if ($company->setting?->work_days_last_refreshed_at)
                    <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                        Ultima actualizacion: {{ $company->setting->work_days_last_refreshed_at->timezone($company->setting->default_timezone ?: $company->timezone)->format('Y-m-d H:i') }}
                        - {{ $company->setting->work_days_last_refresh_status }}
                    </div>
                @endif

                <div class="rounded-md border border-sky-100 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-950 dark:bg-sky-950/40 dark:text-sky-100">
                    <p class="font-medium">Al ejecutar se actualizara:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>Jornadas programadas desde horarios publicados del rango.</li>
                        <li>Jornadas no programadas cuando existan eventos validos sin horario publicado.</li>
                        <li>Eventos anulados y capturas pendientes de revision quedan fuera.</li>
                    </ul>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 p-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="closeRefreshPanel">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Actualizar</flux:button>
            </div>
        </form>
    </x-side-panel>
</section>
