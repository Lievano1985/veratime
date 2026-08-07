<?php

use App\Domains\Alerts\Actions\ResolveAlertAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\WorkDays\Actions\ListWorkDaysAction;
use App\Domains\WorkDays\Actions\ProcessCompanyWorkDaysAction;
use App\Models\Alert;
use App\Models\WorkDayCalculation;
use App\Models\WorkDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public array $processForm = [];

    public bool $showProcessPanel = false;

    public bool $showAlertsPanel = false;

    public ?int $selectedWorkDayId = null;

    public ?int $selectedAlertId = null;

    public array $alertResolutionForm = [
        'status' => Alert::STATUS_JUSTIFIED,
        'resolution' => '',
    ];

    public function mount(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $today = CarbonImmutable::now($company->setting?->default_timezone ?: $company->timezone)
            ->startOfWeek(\Carbon\CarbonInterface::MONDAY);

        $this->dateFrom = $this->dateFrom !== '' ? $this->dateFrom : $today->toDateString();
        $this->dateTo = $this->dateTo !== '' ? $this->dateTo : $today->addDays(6)->toDateString();
        $this->loadProcessForm($company);
    }

    public function openProcessPanel(CurrentCompany $currentCompany, ProcessCompanyWorkDaysAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $this->loadProcessForm($company, $action);
        $this->showProcessPanel = true;
    }

    public function closeProcessPanel(): void
    {
        $this->showProcessPanel = false;
    }

    public function openAlertsPanel(int $workDayId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $workDay = WorkDay::query()
            ->with(['alerts' => fn ($query) => $query->whereIn('status', Alert::OPEN_STATUSES)->orderBy('severity')->orderBy('detected_at')])
            ->where('company_id', $company->id)
            ->findOrFail($workDayId);

        Gate::authorize('view', $workDay);

        $firstAlert = $workDay->alerts->first();
        if (! $firstAlert) {
            Session::flash('status', 'La jornada no tiene alertas abiertas.');
            return;
        }

        $this->selectedWorkDayId = $workDay->id;
        $this->selectedAlertId = $firstAlert->id;
        $this->alertResolutionForm = [
            'status' => Alert::STATUS_JUSTIFIED,
            'resolution' => '',
        ];
        $this->showAlertsPanel = true;
    }

    public function closeAlertsPanel(): void
    {
        $this->showAlertsPanel = false;
        $this->selectedWorkDayId = null;
        $this->selectedAlertId = null;
        $this->alertResolutionForm = [
            'status' => Alert::STATUS_JUSTIFIED,
            'resolution' => '',
        ];
    }

    public function resolveSelectedAlert(ResolveAlertAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $validated = $this->validate([
            'selectedAlertId' => [
                'required',
                'integer',
                Rule::exists('alerts', 'id')->where('company_id', $company->id),
            ],
            'alertResolutionForm.status' => ['required', Rule::in([Alert::STATUS_JUSTIFIED, Alert::STATUS_CORRECTED, Alert::STATUS_CLOSED])],
            'alertResolutionForm.resolution' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $alert = Alert::query()
            ->where('company_id', $company->id)
            ->where('work_day_id', $this->selectedWorkDayId)
            ->findOrFail((int) $validated['selectedAlertId']);

        try {
            $action->handle($company, $alert, auth()->user(), $validated['alertResolutionForm']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'alertResolutionForm.resolution' => $exception->getMessage(),
            ]);
        }

        $nextAlertId = Alert::query()
            ->where('company_id', $company->id)
            ->where('work_day_id', $this->selectedWorkDayId)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->orderBy('detected_at')
            ->value('id');

        if (! $nextAlertId) {
            $this->closeAlertsPanel();
            Session::flash('status', 'Alerta dictaminada. La jornada quedo sin alertas abiertas.');
            $this->resetPage();
            return;
        }

        $this->selectedAlertId = (int) $nextAlertId;
        $this->alertResolutionForm['resolution'] = '';
        Session::flash('status', 'Alerta dictaminada. Aun quedan alertas abiertas para la jornada.');
        $this->resetPage();
    }

    public function processWorkDays(ProcessCompanyWorkDaysAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [WorkDay::class, $company]);

        $validated = $this->validate([
            'processForm.date_from' => ['nullable', 'date', 'required_with:processForm.date_to'],
            'processForm.date_to' => ['nullable', 'date', 'required_with:processForm.date_from', 'after_or_equal:processForm.date_from'],
            'processForm.reason' => ['required', 'string', 'min:5', 'max:500'],
        ])['processForm'];

        $startDate = blank($validated['date_from'] ?? null) ? null : CarbonImmutable::parse($validated['date_from'])->toDateString();
        $endDate = blank($validated['date_to'] ?? null) ? null : CarbonImmutable::parse($validated['date_to'])->toDateString();

        $result = $action->handle(
            $company,
            $startDate,
            $endDate,
            actor: auth()->user(),
            mode: 'manual_ui',
            generatedByType: WorkDayCalculation::GENERATED_BY_USER,
            reason: trim((string) $validated['reason']),
        );

        $this->dateFrom = $result['start_date'];
        $this->dateTo = $result['end_date'];
        $this->showProcessPanel = false;
        $this->resetPage();

        Session::flash('status', "Recalculo de jornadas: {$result['total']} actualizadas, {$result['calculated']} calculadas, {$result['under_review']} en revision, {$result['skipped']} sin eventos validos, {$result['special_legal_cases']} con casos especiales y {$result['alerts_created_or_updated']} alertas revisadas.");
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

        $selectedWorkDay = $this->selectedWorkDayId
            ? WorkDay::query()
                ->with([
                    'worker',
                    'center',
                    'employmentRelationship',
                    'activeCalculation',
                    'alerts' => fn ($query) => $query->with(['alertType', 'resolver'])->orderByRaw("case status when 'new' then 1 when 'in_review' then 2 when 'pending_information' then 3 else 4 end")->orderBy('detected_at'),
                ])
                ->where('company_id', $company->id)
                ->find($this->selectedWorkDayId)
            : null;

        return [
            'company' => $company,
            'centers' => $company->centers()->where('status', 'active')->orderBy('name')->get(),
            'workDays' => $workDays,
            'selectedWorkDay' => $selectedWorkDay,
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
            default => 'Pendiente de calculo',
        };
    }

    private function workDayStatusLabel(WorkDay $workDay): string
    {
        if ($this->isScheduledAbsenceCandidate($workDay)) {
            return 'Falta';
        }

        return $this->statusLabel($workDay->status);
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

    private function workDayStatusVariant(WorkDay $workDay): string
    {
        if ($this->isScheduledAbsenceCandidate($workDay)) {
            return 'danger';
        }

        return $this->statusVariant($workDay->status);
    }

    private function alertStatusLabel(string $status): string
    {
        return match ($status) {
            'new' => 'Nueva',
            'in_review' => 'En revision',
            'pending_information' => 'Pendiente info',
            'justified' => 'Justificada',
            'corrected' => 'Corregida',
            'closed' => 'Cerrada',
            default => ucfirst($status),
        };
    }

    private function alertStatusVariant(string $status): string
    {
        return match ($status) {
            'justified', 'corrected', 'closed' => 'success',
            'in_review', 'pending_information' => 'warning',
            default => 'info',
        };
    }

    private function alertSeverityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Critica',
            'high' => 'Alta',
            'warning' => 'Preventiva',
            default => 'Informativa',
        };
    }

    private function alertSeverityVariant(string $severity): string
    {
        return match ($severity) {
            'critical' => 'danger',
            'high' => 'warning',
            'warning' => 'info',
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

    private function calculationMinutesLabel(?WorkDayCalculation $calculation): string
    {
        if (! $calculation) {
            return 'Sin calculo';
        }

        return $this->minutesLabel($calculation->total_work_minutes);
    }

    private function workedMinutesLabel(WorkDay $workDay): string
    {
        if ($this->isScheduledAbsenceCandidate($workDay)) {
            return 'Falta';
        }

        if (! $workDay->activeCalculation && $workDay->valid_time_event_count === 0) {
            return 'Sin eventos';
        }

        return $this->calculationMinutesLabel($workDay->activeCalculation);
    }

    private function legalClassificationLabel(?WorkDayCalculation $calculation): string
    {
        return match ($calculation?->classification) {
            WorkDayCalculation::CLASSIFICATION_DIURNAL => 'Diurna',
            WorkDayCalculation::CLASSIFICATION_NOCTURNAL => 'Nocturna',
            WorkDayCalculation::CLASSIFICATION_MIXED => 'Mixta',
            default => 'Pendiente',
        };
    }

    private function workDayLegalClassificationLabel(WorkDay $workDay): string
    {
        if (! $workDay->activeCalculation && $workDay->valid_time_event_count === 0) {
            return 'No aplica';
        }

        return $this->legalClassificationLabel($workDay->activeCalculation);
    }

    private function legalClassificationVariant(?WorkDayCalculation $calculation): string
    {
        return match ($calculation?->classification) {
            WorkDayCalculation::CLASSIFICATION_DIURNAL => 'success',
            WorkDayCalculation::CLASSIFICATION_NOCTURNAL => 'info',
            WorkDayCalculation::CLASSIFICATION_MIXED => 'warning',
            default => 'neutral',
        };
    }

    private function workDayLegalClassificationVariant(WorkDay $workDay): string
    {
        if (! $workDay->activeCalculation && $workDay->valid_time_event_count === 0) {
            return 'neutral';
        }

        return $this->legalClassificationVariant($workDay->activeCalculation);
    }

    private function legalNightMinutesLabel(?WorkDayCalculation $calculation): string
    {
        if (! $calculation || $calculation->classification === WorkDayCalculation::CLASSIFICATION_PENDING) {
            return '';
        }

        return $calculation->night_minutes > 0
            ? 'Nocturna: '.$this->minutesLabel($calculation->night_minutes)
            : 'Sin nocturna';
    }

    private function calculationSplitLabel(?WorkDayCalculation $calculation, string $field): string
    {
        if (! $calculation || $calculation->classification === WorkDayCalculation::CLASSIFICATION_PENDING) {
            return 'Pendiente';
        }

        return $this->minutesLabel((int) $calculation->{$field});
    }

    private function workDayCalculationSplitLabel(WorkDay $workDay, string $field): string
    {
        if (! $workDay->activeCalculation && $workDay->valid_time_event_count === 0) {
            return 'No aplica';
        }

        return $this->calculationSplitLabel($workDay->activeCalculation, $field);
    }

    private function specialCasesLabel(?WorkDayCalculation $calculation): string
    {
        if (! $calculation || $calculation->classification === WorkDayCalculation::CLASSIFICATION_PENDING) {
            return 'Pendiente';
        }

        $labels = [];

        if ($calculation->sunday_minutes > 0) {
            $labels[] = 'Dom '.$this->minutesLabel($calculation->sunday_minutes);
        }

        if ($calculation->mandatory_rest_minutes > 0) {
            $labels[] = 'Obl '.$this->minutesLabel($calculation->mandatory_rest_minutes);
        }

        if ((bool) data_get($calculation->result_snapshot, 'special_legal_cases.weekly_rest.requires_review')) {
            $labels[] = 'Sin descanso semanal';
        }

        return $labels === [] ? 'Sin especiales' : implode(' | ', $labels);
    }

    private function workDaySpecialCasesLabel(WorkDay $workDay): string
    {
        if (! $workDay->activeCalculation && $workDay->valid_time_event_count === 0) {
            return 'No aplica';
        }

        return $this->specialCasesLabel($workDay->activeCalculation);
    }

    private function isScheduledAbsenceCandidate(WorkDay $workDay): bool
    {
        return ! $workDay->activeCalculation
            && $workDay->valid_time_event_count === 0
            && $workDay->schedule_status === WorkDay::SCHEDULE_STATUS_SCHEDULED
            && $workDay->day_type === 'shift'
            && (int) $workDay->expected_work_minutes > 0;
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

    private function loadProcessForm($company, ?ProcessCompanyWorkDaysAction $action = null): void
    {
        $range = $action?->defaultAvailableRange($company);

        $this->processForm = [
            'date_from' => $range['start_date'] ?? '',
            'date_to' => $range['end_date'] ?? '',
            'reason' => '',
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Jornadas</flux:heading>
            <flux:subheading>Consulta jornadas generadas desde horarios publicados y eventos validos.</flux:subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button type="button" variant="primary" wire:click="openProcessPanel">
                Recalcular jornadas
            </flux:button>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

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

            <flux:select label="Horario" wire:model.live="scheduleStatusFilter">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="scheduled">Programadas</flux:select.option>
                <flux:select.option value="unscheduled">No programadas</flux:select.option>
            </flux:select>

            <flux:select label="Calculo" wire:model.live="statusFilter">
                <flux:select.option value="">Todas</flux:select.option>
                <flux:select.option value="with_alerts">Con alertas</flux:select.option>
                <flux:select.option value="calculated">Calculadas</flux:select.option>
                <flux:select.option value="under_review">En revision</flux:select.option>
                <flux:select.option value="pending">Pendientes</flux:select.option>
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
                            <th class="px-4 py-3">Trabajado</th>
                            <th class="px-4 py-3">Ordinario</th>
                            <th class="px-4 py-3">Extra</th>
                            <th class="px-4 py-3">Especiales</th>
                            <th class="px-4 py-3">Legal</th>
                            <th class="px-4 py-3">Eventos</th>
                            <th class="px-4 py-3">Calculo</th>
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
                                <td class="px-4 py-3">{{ $this->workedMinutesLabel($workDay) }}</td>
                                <td class="px-4 py-3">{{ $this->workDayCalculationSplitLabel($workDay, 'ordinary_minutes') }}</td>
                                <td class="px-4 py-3">{{ $this->workDayCalculationSplitLabel($workDay, 'overtime_minutes') }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $this->workDaySpecialCasesLabel($workDay) }}</td>
                                <td class="px-4 py-3">
                                    <x-ui.badge variant="{{ $this->workDayLegalClassificationVariant($workDay) }}">
                                        {{ $this->workDayLegalClassificationLabel($workDay) }}
                                    </x-ui.badge>
                                    @if ($this->legalNightMinutesLabel($workDay->activeCalculation) !== '')
                                        <span class="mt-1 block text-xs text-zinc-500">{{ $this->legalNightMinutesLabel($workDay->activeCalculation) }}</span>
                                    @endif
                                </td>
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
                                    @if ($workDay->status === WorkDay::STATUS_WITH_ALERTS && ($workDay->open_alerts_count ?? 0) > 0)
                                        <button
                                            type="button"
                                            wire:click="openAlertsPanel({{ $workDay->id }})"
                                            class="inline-flex items-center rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800 shadow-sm transition hover:border-amber-300 hover:bg-amber-100 focus:outline-none focus:ring-2 focus:ring-amber-200 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-200 dark:hover:bg-amber-900/60"
                                            title="Atender alertas de la jornada"
                                        >
                                            Alerta ({{ $workDay->open_alerts_count }})
                                        </button>
                                    @else
                                        <x-ui.badge variant="{{ $this->workDayStatusVariant($workDay) }}">
                                            {{ $this->workDayStatusLabel($workDay) }}
                                        </x-ui.badge>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-4 py-8 text-center text-zinc-500">Sin jornadas en el rango seleccionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $workDays->links() }}
    </section>

    <x-side-panel
        wire:model="showAlertsPanel"
        title="Alertas de jornada"
        subheading="Dictamina alertas abiertas sin recalcular la jornada."
        labelledby="work-day-alerts-title"
        maxWidth="max-w-2xl"
        closeMethod="closeAlertsPanel"
    >
        <div class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-5 p-6">
                @if ($selectedWorkDay)
                    <section class="rounded-md border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800/60">
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Trabajador</dt>
                                <dd class="mt-1 font-medium">{{ $selectedWorkDay->worker?->employee_code }} - {{ $selectedWorkDay->worker?->full_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Fecha</dt>
                                <dd class="mt-1 font-medium">{{ $selectedWorkDay->work_date?->toDateString() }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Centro</dt>
                                <dd class="mt-1">{{ $selectedWorkDay->center?->name ?? 'Sin centro' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase text-zinc-500">Calculado</dt>
                                <dd class="mt-1">{{ $this->workedMinutesLabel($selectedWorkDay) }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="space-y-3">
                        <flux:heading>Alertas</flux:heading>

                        @foreach ($selectedWorkDay->alerts as $alert)
                            <label class="block rounded-md border border-zinc-200 p-4 dark:border-zinc-700 {{ $selectedAlertId === $alert->id ? 'bg-blue-50 dark:bg-blue-950/30' : 'bg-white dark:bg-zinc-900' }}">
                                <div class="flex items-start gap-3">
                                    @if (in_array($alert->status, Alert::OPEN_STATUSES, true))
                                        <input type="radio" class="mt-1" wire:model.live="selectedAlertId" value="{{ $alert->id }}">
                                    @else
                                        <span class="mt-1 h-4 w-4 rounded-full bg-zinc-200 dark:bg-zinc-700"></span>
                                    @endif

                                    <div class="min-w-0 flex-1 space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium">{{ $alert->title }}</p>
                                            <x-ui.badge variant="{{ $this->alertSeverityVariant($alert->severity) }}">
                                                {{ $this->alertSeverityLabel($alert->severity) }}
                                            </x-ui.badge>
                                            <x-ui.badge variant="{{ $this->alertStatusVariant($alert->status) }}">
                                                {{ $this->alertStatusLabel($alert->status) }}
                                            </x-ui.badge>
                                        </div>

                                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $alert->description }}</p>
                                        <p class="text-xs text-zinc-500">{{ $alert->alertType?->code }} - Detectada {{ $alert->detected_at?->timezone($selectedWorkDay->timezone ?: $company->timezone)->format('Y-m-d H:i') }}</p>

                                        @if ($alert->resolution)
                                            <div class="rounded-md bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                                                <p class="font-medium">Dictamen</p>
                                                <p class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $alert->resolution }}</p>
                                                <p class="mt-1 text-xs text-zinc-500">
                                                    {{ $alert->resolver?->name ?? 'Usuario no disponible' }}
                                                    @if ($alert->resolved_at)
                                                        - {{ $alert->resolved_at->timezone($selectedWorkDay->timezone ?: $company->timezone)->format('Y-m-d H:i') }}
                                                    @endif
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </section>

                    @if ($selectedWorkDay->alerts->contains(fn ($alert) => in_array($alert->status, Alert::OPEN_STATUSES, true)))
                        <form wire:submit="resolveSelectedAlert" class="space-y-4 rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
                            <flux:select label="Dictamen" wire:model="alertResolutionForm.status">
                                <flux:select.option value="justified">Justificada</flux:select.option>
                                <flux:select.option value="corrected">Corregida</flux:select.option>
                                <flux:select.option value="closed">Cerrada / no procede</flux:select.option>
                            </flux:select>

                            <flux:textarea label="Motivo" wire:model="alertResolutionForm.resolution" rows="4" placeholder="Describe la aclaracion o decision operativa." />

                            <div class="flex justify-end gap-2">
                                <flux:button type="button" variant="ghost" wire:click="closeAlertsPanel">Cancelar</flux:button>
                                <flux:button type="submit" variant="primary">Guardar dictamen</flux:button>
                            </div>
                        </form>
                    @else
                        <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                            Esta jornada no tiene alertas abiertas.
                        </div>
                    @endif
                @else
                    <p class="text-sm text-zinc-500">Selecciona una jornada con alertas.</p>
                @endif
            </div>
        </div>
    </x-side-panel>

    <x-side-panel
        wire:model="showProcessPanel"
        title="Recalcular jornadas"
        subheading="Reprocesa jornadas disponibles y conserva motivo de auditoria."
        labelledby="process-work-days-title"
    >
        <form wire:submit="processWorkDays" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <div class="rounded-md border border-zinc-200 bg-zinc-50 p-4 text-sm dark:border-zinc-700 dark:bg-zinc-800/60">
                    <dl class="grid gap-3">
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Empresa</dt>
                            <dd class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $company->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">Alcance</dt>
                            <dd class="mt-1 text-zinc-700 dark:text-zinc-200">Datos disponibles hasta hoy. Las fechas permiten reprocesar un rango puntual.</dd>
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

                <div class="grid gap-3 sm:grid-cols-2">
                    <flux:input wire:model="processForm.date_from" label="Desde" type="date" />
                    <flux:input wire:model="processForm.date_to" label="Hasta" type="date" />
                </div>
                <flux:textarea wire:model="processForm.reason" label="Motivo obligatorio" placeholder="Ej. Reproceso por checadas capturadas tarde o revision de RH." rows="3" />

                @if ($company->setting?->work_days_last_refreshed_at)
                    <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                        Ultima ejecucion: {{ $company->setting->work_days_last_refreshed_at->timezone($company->setting->default_timezone ?: $company->timezone)->format('Y-m-d H:i') }}
                        - {{ $company->setting->work_days_last_refresh_status }}
                    </div>
                @endif

                <div class="rounded-md border border-amber-100 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-950 dark:bg-amber-950/40 dark:text-amber-100">
                    <p class="font-medium">Al ejecutar se procesara:</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        <li>Jornadas programadas desde horarios publicados.</li>
                        <li>Jornadas no programadas cuando existan eventos validos sin horario.</li>
                        <li>Usa eventos validos ordenados por fecha real del hecho.</li>
                        <li>Genera una version activa y conserva versiones anteriores.</li>
                        <li>Clasifica la jornada como diurna, nocturna o mixta.</li>
                        <li>Calcula minutos ordinarios y extra con reglas versionadas.</li>
                        <li>Identifica trabajo en domingo, descanso obligatorio y semanas sin descanso detectado.</li>
                        <li>Genera o cierra alertas preventivas segun el calculo vigente.</li>
                        <li>No genera incidencias ni cierres.</li>
                    </ul>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 p-4 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="closeProcessPanel">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Recalcular</flux:button>
            </div>
        </form>
    </x-side-panel>
</section>
