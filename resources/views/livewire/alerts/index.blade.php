<?php

use App\Domains\Alerts\Actions\ListAlertsAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Alert;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
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

    #[Url]
    public string $status = '';

    #[Url]
    public string $severity = '';

    #[Url]
    public string $search = '';

    public function mount(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [Alert::class, $company]);

        $today = CarbonImmutable::now($company->setting?->default_timezone ?: $company->timezone)
            ->startOfWeek(\Carbon\CarbonInterface::MONDAY);

        $this->dateFrom = $this->dateFrom !== '' ? $this->dateFrom : $today->toDateString();
        $this->dateTo = $this->dateTo !== '' ? $this->dateTo : $today->addDays(6)->toDateString();
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

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSeverity(): void
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
        $this->status = '';
        $this->severity = '';
        $this->search = '';
        $this->resetPage();
    }

    public function with(CurrentCompany $currentCompany, ListAlertsAction $listAlerts): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [Alert::class, $company]);

        return [
            'company' => $company,
            'centers' => $company->centers()->where('status', 'active')->orderBy('name')->get(),
            'alerts' => $listAlerts->handle($company, [
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
                'center_id' => $this->centerId === '' ? null : (int) $this->centerId,
                'status' => $this->status === '' ? null : $this->status,
                'severity' => $this->severity === '' ? null : $this->severity,
                'search' => $this->search,
            ]),
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function severityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Critica',
            'high' => 'Alta',
            'warning' => 'Preventiva',
            default => 'Informativa',
        };
    }

    private function severityVariant(string $severity): string
    {
        return match ($severity) {
            'critical' => 'danger',
            'high' => 'warning',
            'warning' => 'info',
            default => 'neutral',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'Nueva',
            'in_review' => 'En revision',
            'pending_information' => 'Pendiente info',
            'justified' => 'Justificada',
            'corrected' => 'Corregida',
            'closed' => 'Cerrada',
            default => 'Nueva',
        };
    }

    private function statusVariant(string $status): string
    {
        return match ($status) {
            'closed', 'corrected', 'justified' => 'success',
            'in_review', 'pending_information' => 'warning',
            default => 'info',
        };
    }

    private function workDayLabel(Alert $alert): string
    {
        if ($alert->alertType?->code === 'weekly_rest_missing') {
            $weekStart = data_get($alert->metadata, 'week_start');
            $weekEnd = data_get($alert->metadata, 'week_end');

            if ($weekStart && $weekEnd) {
                return "{$weekStart} a {$weekEnd}";
            }
        }

        return $alert->workDay?->work_date?->toDateString() ?? 'Sin jornada';
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">Alertas</flux:heading>
        <flux:subheading>Consulta alertas preventivas generadas desde jornadas calculadas.</flux:subheading>
    </div>

    <section class="rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/60">
        <div class="grid gap-3 lg:grid-cols-[1fr_1fr_1fr_1fr_1fr_1fr_auto] lg:items-end">
            <flux:input label="Desde" type="date" wire:model.live="dateFrom" />
            <flux:input label="Hasta" type="date" wire:model.live="dateTo" />

            <flux:select label="Centro" wire:model.live="centerId">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select label="Estado" wire:model.live="status">
                <flux:select.option value="">Abiertas</flux:select.option>
                <flux:select.option value="new">Nuevas</flux:select.option>
                <flux:select.option value="in_review">En revision</flux:select.option>
                <flux:select.option value="closed">Cerradas</flux:select.option>
            </flux:select>

            <flux:select label="Severidad" wire:model.live="severity">
                <flux:select.option value="">Todas</flux:select.option>
                <flux:select.option value="critical">Critica</flux:select.option>
                <flux:select.option value="high">Alta</flux:select.option>
                <flux:select.option value="warning">Preventiva</flux:select.option>
                <flux:select.option value="informational">Informativa</flux:select.option>
            </flux:select>

            <flux:input label="Buscar" placeholder="Trabajador o alerta" wire:model.live.debounce.400ms="search" />

            <flux:button type="button" variant="ghost" wire:click="clearFilters">Limpiar</flux:button>
        </div>
    </section>

    <section class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:heading>Listado preventivo</flux:heading>
            <p class="text-sm text-zinc-500">{{ $alerts->total() }} encontradas</p>
        </div>

        <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3">Alerta</th>
                            <th class="px-4 py-3">Trabajador</th>
                            <th class="px-4 py-3">Jornada</th>
                            <th class="px-4 py-3">Centro</th>
                            <th class="px-4 py-3">Severidad</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3">Detectada</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                        @forelse ($alerts as $alert)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $alert->title }}</div>
                                    <div class="text-xs text-zinc-500">{{ $alert->alertType?->code }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $alert->worker?->employee_code }} - {{ $alert->worker?->full_name }}</div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $this->workDayLabel($alert) }}</td>
                                <td class="px-4 py-3">{{ $alert->workDay?->center?->name ?? 'Sin centro' }}</td>
                                <td class="px-4 py-3">
                                    <x-ui.badge variant="{{ $this->severityVariant($alert->severity) }}">
                                        {{ $this->severityLabel($alert->severity) }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-4 py-3">
                                    <x-ui.badge variant="{{ $this->statusVariant($alert->status) }}">
                                        {{ $this->statusLabel($alert->status) }}
                                    </x-ui.badge>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    {{ $alert->detected_at?->timezone($company->setting?->default_timezone ?: $company->timezone)->format('Y-m-d H:i') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-zinc-500">Sin alertas en el rango seleccionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $alerts->links() }}
    </section>
</section>
