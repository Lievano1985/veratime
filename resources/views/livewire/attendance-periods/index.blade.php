<?php

use App\Domains\Attendance\Actions\CancelAttendancePeriodAction;
use App\Domains\Attendance\Actions\CloseAttendancePeriodAction;
use App\Domains\Attendance\Actions\CreateAttendancePeriodAction;
use App\Domains\Attendance\Actions\ListAttendancePeriodsAction;
use App\Domains\Attendance\Actions\SuggestAttendancePeriodRangeAction;
use App\Domains\Attendance\Actions\ValidateAttendancePeriodForClosingAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\ExportLayouts\Actions\DownloadAttendancePeriodLayoutAction;
use App\Models\ExportLayout;
use App\Models\AttendancePeriod;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public array $filters = [];
    public array $form = [];
    public array $cancelForm = [];
    public bool $showExportPanel = false;
    public ?int $exportingPeriodId = null;
    public bool $showCreatePanel = false;
    public bool $showCancelPanel = false;
    public ?int $cancellingPeriodId = null;
    public ?int $selectedPeriodId = null;

    public function mount(): void
    {
        $this->filters = ['center_id' => '', 'status' => '', 'date_from' => '', 'date_to' => ''];
        $this->form = $this->emptyForm();
        $this->cancelForm = ['reason' => ''];
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.')) {
            $this->resetPage();
        }

        if ($property === 'form.center_id') {
            $this->form['organizational_unit_ids'] = [];
            $this->suggestRange();
        }

        if ($property === 'form.scope_mode') {
            $this->form['organizational_unit_ids'] = [];
        }
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [AttendancePeriod::class, $company]);

        $this->form = $this->emptyForm();
        $firstCenter = $company->centers()->where('status', 'active')->orderBy('name')->first();

        if ($firstCenter) {
            $this->form['center_id'] = (string) $firstCenter->id;
            $this->suggestRange();
        }

        $this->showCreatePanel = true;
    }

    public function suggestRange(?CurrentCompany $currentCompany = null, ?SuggestAttendancePeriodRangeAction $action = null): void
    {
        $company = $currentCompany ? $this->currentCompanyOrFail($currentCompany) : app(CurrentCompany::class)->get();

        if (! $company || blank($this->form['center_id'] ?? null)) {
            return;
        }

        $center = $company->centers()->where('status', 'active')->whereKey((int) $this->form['center_id'])->first();
        if (! $center) {
            return;
        }

        $range = ($action ?: app(SuggestAttendancePeriodRangeAction::class))->handle($company, $center);
        $this->form['period_start'] = $range['period_start'];
        $this->form['period_end'] = $range['period_end'];
    }

    public function create(CurrentCompany $currentCompany, CreateAttendancePeriodAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [AttendancePeriod::class, $company]);

        $validated = $this->validate([
            'form.center_id' => ['required', 'integer', Rule::exists('centers', 'id')->where('company_id', $company->id)->where('status', 'active')],
            'form.scope_mode' => ['required', Rule::in(['center', 'units'])],
            'form.organizational_unit_ids' => ['array'],
            'form.organizational_unit_ids.*' => ['integer'],
            'form.period_start' => ['required', 'date'],
            'form.period_end' => ['required', 'date', 'after_or_equal:form.period_start'],
            'form.name' => ['nullable', 'string', 'max:255'],
            'form.notes' => ['nullable', 'string', 'max:1000'],
        ])['form'];

        $center = $company->centers()->whereKey((int) $validated['center_id'])->firstOrFail();
        $unitIds = $validated['scope_mode'] === 'units'
            ? array_values(array_unique(array_map('intval', $validated['organizational_unit_ids'] ?? [])))
            : [];

        if ($validated['scope_mode'] === 'units' && $unitIds === []) {
            throw ValidationException::withMessages(['form.organizational_unit_ids' => 'Selecciona al menos una unidad o usa todo el centro.']);
        }

        try {
            $action->handle($company, $center, [
                'period_start' => $validated['period_start'],
                'period_end' => $validated['period_end'],
                'name' => $validated['name'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ], $unitIds, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['form.period_start' => $exception->getMessage()]);
        }

        $this->showCreatePanel = false;
        $this->form = $this->emptyForm();
        $this->resetPage();

        Session::flash('status', 'Periodo de asistencia generado.');
    }

    public function openCancelPanel(int $periodId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $period = $this->periodForCompany($company, $periodId);

        Gate::authorize('cancel', $period);

        $this->cancellingPeriodId = $period->id;
        $this->cancelForm = ['reason' => ''];
        $this->showCancelPanel = true;
    }

    public function cancel(CurrentCompany $currentCompany, CancelAttendancePeriodAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $period = $this->periodForCompany($company, (int) $this->cancellingPeriodId);

        Gate::authorize('cancel', $period);

        $validated = $this->validate([
            'cancelForm.reason' => ['required', 'string', 'max:1000'],
        ])['cancelForm'];

        try {
            $action->handle($company, $period, $validated['reason'], auth()->user());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['cancelForm.reason' => $exception->getMessage()]);
        }

        $this->showCancelPanel = false;
        $this->cancellingPeriodId = null;
        $this->cancelForm = ['reason' => ''];

        Session::flash('status', 'Periodo cancelado.');
    }

    public function selectPeriod(int $periodId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $period = $this->periodForCompany($company, $periodId);

        Gate::authorize('view', $period);

        $this->selectedPeriodId = $period->id;
    }

    public function validatePeriod(int $periodId, CurrentCompany $currentCompany, ValidateAttendancePeriodForClosingAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $period = $this->periodForCompany($company, $periodId);

        Gate::authorize('validateForClosing', $period);

        $summary = $action->handle($company, $period, auth()->user());
        $this->selectedPeriodId = $period->id;

        Session::flash('status', ($summary['ready_to_close'] ?? false)
            ? 'Periodo listo para cierre.'
            : 'El periodo tiene bloqueantes pendientes en Jornadas.');
    }

    public function closePeriod(int $periodId, CurrentCompany $currentCompany, CloseAttendancePeriodAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $period = $this->periodForCompany($company, $periodId);

        Gate::authorize('close', $period);

        try {
            $closed = $action->handle($company, $period, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['closePeriod' => $exception->getMessage()]);
        }

        $this->selectedPeriodId = $closed->id;

        Session::flash('status', 'Periodo cerrado y reporte base generado.');
    }

    public function openExportPanel(int $periodId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $period = $this->periodForCompany($company, $periodId);
        Gate::authorize('exportPayrollXlsx', $period);
        $this->exportingPeriodId = $period->id;
        $this->resetValidation('export');
        $this->showExportPanel = true;
    }

    public function downloadLayout(CurrentCompany $currentCompany, DownloadAttendancePeriodLayoutAction $action): mixed
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $period = $this->periodForCompany($company, (int) $this->exportingPeriodId);

        return $action->handle(auth()->user(), $company, $period);
    }

    public function closeCreatePanel(): void
    {
        $this->showCreatePanel = false;
        $this->form = $this->emptyForm();
        $this->resetValidation('form');
    }

    public function closeCancelPanel(): void
    {
        $this->showCancelPanel = false;
        $this->cancellingPeriodId = null;
        $this->cancelForm = ['reason' => ''];
        $this->resetValidation('cancelForm');
    }

    public function with(CurrentCompany $currentCompany, ListAttendancePeriodsAction $listPeriods): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [AttendancePeriod::class, $company]);

        $centers = $company->centers()->where('status', 'active')->orderBy('name')->get();
        $selectedCenterId = filled($this->form['center_id'] ?? null) ? (int) $this->form['center_id'] : null;

        return [
            'defaultExportLayout' => ExportLayout::where('company_id', $company->id)->where('status', 'active')->where('is_default', true)->first(),
            'exportingPeriod' => $this->exportingPeriodId ? $this->periodForCompany($company, $this->exportingPeriodId) : null,
            'centers' => $centers,
            'periods' => $listPeriods->handle($company, $this->filters),
            'selectedPeriod' => $this->selectedPeriodId ? $this->periodForCompany($company, $this->selectedPeriodId) : null,
            'availableUnits' => $selectedCenterId
                ? $company->organizationalUnits()->where('center_id', $selectedCenterId)->where('status', 'active')->orderBy('name')->get()
                : collect(),
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function periodForCompany($company, int $periodId): AttendancePeriod
    {
        return AttendancePeriod::query()
            ->with(['center', 'creator', 'validatedBy', 'closedBy', 'scopes.organizationalUnit'])
            ->where('company_id', $company->id)
            ->findOrFail($periodId);
    }

    private function emptyForm(): array
    {
        return [
            'center_id' => '',
            'scope_mode' => 'center',
            'organizational_unit_ids' => [],
            'period_start' => '',
            'period_end' => '',
            'name' => '',
            'notes' => '',
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            AttendancePeriod::STATUS_OPEN => 'Abierto',
            AttendancePeriod::STATUS_READY => 'Listo',
            AttendancePeriod::STATUS_CLOSED => 'Cerrado',
            AttendancePeriod::STATUS_CANCELLED => 'Cancelado',
            default => ucfirst($status),
        };
    }

    private function blockerCount(AttendancePeriod $period): int
    {
        return (int) data_get($period->validation_summary, 'blockers.total', 0);
    }

    private function minutesLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours === 0) {
            return "{$remaining} min";
        }

        return $remaining === 0 ? "{$hours} h" : "{$hours} h {$remaining} min";
    }

    private function workDaysUrl(AttendancePeriod $period): string
    {
        return route('work-days.index', [
            'from' => $period->period_start?->toDateString(),
            'to' => $period->period_end?->toDateString(),
            'center' => $period->center_id,
            'incident' => 'with_incidents',
        ]);
    }

    private function scopeLabel(AttendancePeriod $period): string
    {
        if ($period->scope_type === AttendancePeriod::SCOPE_CENTER) {
            return 'Todo el centro';
        }

        return $period->scopes
            ->pluck('organizationalUnit.name')
            ->filter()
            ->join(', ');
    }
}; ?>

<section class="w-full space-y-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Exportar períodos</flux:heading>
            <flux:subheading>Genera paquetes de asistencia por centro, unidades y rango. Vera Time no calcula nomina.</flux:subheading>
        </div>

        <button type="button" class="btn-primary" wire:click="openCreatePanel">
            <span class="text-base leading-none">+</span>
            Nuevo periodo
        </button>
    </div>

    @error('payroll_export')
        <p role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900">{{ $message }}</p>
    @enderror
    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
        <div class="grid gap-4 lg:grid-cols-4">
            <flux:select label="Centro" wire:model.live="filters.center_id">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select label="Estado" wire:model.live="filters.status">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="open">Abiertos</flux:select.option>
                <flux:select.option value="ready">Listos</flux:select.option>
                <flux:select.option value="closed">Cerrados</flux:select.option>
                <flux:select.option value="cancelled">Cancelados</flux:select.option>
            </flux:select>
            <flux:input type="date" label="Desde" wire:model.live="filters.date_from" />
            <flux:input type="date" label="Hasta" wire:model.live="filters.date_to" />
        </div>
    </section>

    <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
        <table class="w-full divide-y divide-zinc-200 text-sm">
            <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500">
                <tr>
                    <th class="px-4 py-3">Centro</th>
                    <th class="px-4 py-3">Alcance</th>
                    <th class="px-4 py-3">Periodo</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Creado por</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200">
                @forelse ($periods as $period)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-medium text-zinc-900">{{ $period->center->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $period->name ?: 'Sin nombre adicional' }}</div>
                        </td>
                        <td class="px-4 py-3 text-zinc-700">{{ $this->scopeLabel($period) ?: 'Sin unidades' }}</td>
                        <td class="px-4 py-3">
                            {{ $period->period_start?->format('Y-m-d') }} - {{ $period->period_end?->format('Y-m-d') }}
                        </td>
                        <td class="px-4 py-3">
                            <x-ui.badge variant="{{ $period->status === 'open' ? 'info' : ($period->status === 'closed' ? 'success' : 'neutral') }}">
                                {{ $this->statusLabel($period->status) }}
                            </x-ui.badge>
                            @if ($period->validation_summary)
                                <div class="mt-1 text-xs text-zinc-500">
                                    {{ $this->blockerCount($period) }} bloqueantes
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-zinc-600">{{ $period->creator?->name ?? 'Sistema' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                                <flux:button type="button" size="sm" variant="ghost" wire:click="selectPeriod({{ $period->id }})">
                                    {{ $period->status === 'closed' ? 'Reporte' : 'Ver' }}
                                </flux:button>
                                @can('validateForClosing', $period)
                                    <button type="button" class="btn btn-sm" wire:click="validatePeriod({{ $period->id }})" wire:loading.attr="disabled" wire:target="validatePeriod({{ $period->id }})">
                                        <span wire:loading wire:target="validatePeriod({{ $period->id }})" class="btn-spinner"></span>
                                        <span wire:loading.remove wire:target="validatePeriod({{ $period->id }})">Validar</span>
                                        <span wire:loading wire:target="validatePeriod({{ $period->id }})">Validando</span>
                                    </button>
                                @endcan
                                @can('close', $period)
                                    <button type="button" class="btn-primary btn-sm" wire:click="closePeriod({{ $period->id }})" wire:loading.attr="disabled" wire:target="closePeriod({{ $period->id }})">
                                        <span wire:loading wire:target="closePeriod({{ $period->id }})" class="btn-spinner"></span>
                                        <span wire:loading.remove wire:target="closePeriod({{ $period->id }})">Cerrar</span>
                                        <span wire:loading wire:target="closePeriod({{ $period->id }})">Cerrando</span>
                                    </button>
                                @endcan
                                @can('cancel', $period)
                                    <flux:button type="button" size="sm" variant="danger" wire:click="openCancelPanel({{ $period->id }})">
                                        Cancelar
                                    </flux:button>
                                @endcan
                                @can('exportPayrollXlsx', $period)
                                    @if ($period->hasFrozenPayrollExport())
                                        <button type="button" class="btn-primary btn-sm" wire:click="openExportPanel({{ $period->id }})" wire:loading.attr="disabled">Exportar con layout</button>
                                    @else
                                        <span class="text-xs text-amber-700">Cierre anterior: solo resumen histórico.</span>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                            Aun no hay periodos de asistencia.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="border-t border-zinc-200 px-4 py-3">
            {{ $periods->links() }}
        </div>
    </section>

    @error('closePeriod')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    @if ($selectedPeriod)
        <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="text-sm font-semibold text-zinc-900">
                        {{ $selectedPeriod->center->name }} - {{ $selectedPeriod->period_start?->format('Y-m-d') }} a {{ $selectedPeriod->period_end?->format('Y-m-d') }}
                    </div>
                    <div class="text-xs text-zinc-500">
                        {{ $this->scopeLabel($selectedPeriod) }} - {{ $this->statusLabel($selectedPeriod->status) }}
                    </div>
                </div>

                @if ($selectedPeriod->validation_summary && $this->blockerCount($selectedPeriod) > 0)
                    <a href="{{ $this->workDaysUrl($selectedPeriod) }}" wire:navigate class="inline-flex items-center rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm font-medium text-zinc-700 shadow-sm hover:bg-zinc-50">
                        Ver bloqueantes en Jornadas
                    </a>
                @endif
            </div>

            @if ($selectedPeriod->validation_summary)
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-md bg-zinc-50 p-3 text-sm">
                        <div class="text-xs text-zinc-500">Jornadas</div>
                        <div class="font-semibold">{{ data_get($selectedPeriod->validation_summary, 'work_days', 0) }}</div>
                    </div>
                    <div class="rounded-md bg-zinc-50 p-3 text-sm">
                        <div class="text-xs text-zinc-500">Alertas abiertas</div>
                        <div class="font-semibold">{{ data_get($selectedPeriod->validation_summary, 'blockers.open_alerts', 0) }}</div>
                    </div>
                    <div class="rounded-md bg-zinc-50 p-3 text-sm">
                        <div class="text-xs text-zinc-500">Jornadas por resolver</div>
                        <div class="font-semibold">{{ data_get($selectedPeriod->validation_summary, 'blockers.unresolved_work_days', data_get($selectedPeriod->validation_summary, 'blockers.pending_or_under_review_work_days', 0)) }}</div>
                    </div>
                    <div class="rounded-md bg-zinc-50 p-3 text-sm">
                        <div class="text-xs text-zinc-500">Resultado</div>
                        <div class="font-semibold">{{ data_get($selectedPeriod->validation_summary, 'ready_to_close') ? 'Listo para cerrar' : 'Con bloqueantes' }}</div>
                    </div>
                </div>
            @else
                <div class="rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800">
                    Valida el periodo para confirmar si existen bloqueantes pendientes en Jornadas.
                </div>
            @endif

            @if ($selectedPeriod->status === 'closed' && $selectedPeriod->report_summary)
                @if ($selectedPeriod->hasFrozenPayrollExport())
                    <p class="rounded-lg bg-blue-50 p-3 text-sm text-blue-900">El resumen y las descargas conservan los datos del cierre, aunque después se corrijan jornadas o datos del trabajador.</p>
                @else
                    <p class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Este cierre conserva su resumen original, pero no el detalle histórico para generar CSV o Excel. No se sustituirá por datos actuales.</p>
                @endif
                @php($summary = $selectedPeriod->report_summary['summary'] ?? [])
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                    <div class="rounded-md bg-zinc-50 p-3 text-sm"><div class="text-xs text-zinc-500">Trabajadores</div><div class="font-semibold">{{ $summary['workers_included'] ?? 0 }}</div></div>
                    <div class="rounded-md bg-zinc-50 p-3 text-sm"><div class="text-xs text-zinc-500">Jornadas</div><div class="font-semibold">{{ $summary['programmed_days'] ?? 0 }}</div></div>
                    <div class="rounded-md bg-zinc-50 p-3 text-sm"><div class="text-xs text-zinc-500">Asistencias</div><div class="font-semibold">{{ $summary['attendances'] ?? 0 }}</div></div>
                    <div class="rounded-md bg-zinc-50 p-3 text-sm"><div class="text-xs text-zinc-500">Faltas</div><div class="font-semibold">{{ $summary['absences'] ?? 0 }}</div></div>
                    <div class="rounded-md bg-zinc-50 p-3 text-sm"><div class="text-xs text-zinc-500">Ordinario</div><div class="font-semibold">{{ $this->minutesLabel((int) ($summary['ordinary_minutes'] ?? 0)) }}</div></div>
                    <div class="rounded-md bg-zinc-50 p-3 text-sm"><div class="text-xs text-zinc-500">Extra aprobado</div><div class="font-semibold">{{ $this->minutesLabel((int) ($summary['overtime_minutes'] ?? 0)) }}</div></div>
                    <div class="rounded-md bg-zinc-50 p-3 text-sm"><div class="text-xs text-zinc-500">Extra doble</div><div class="font-semibold">{{ $this->minutesLabel((int) ($summary['overtime_double_minutes'] ?? 0)) }}</div></div>
                    <div class="rounded-md bg-zinc-50 p-3 text-sm"><div class="text-xs text-zinc-500">Extra triple</div><div class="font-semibold">{{ $this->minutesLabel((int) ($summary['overtime_triple_minutes'] ?? 0)) }}</div></div>
                </div>

                <div class="overflow-hidden rounded-lg border border-zinc-200">
                    <table class="w-full divide-y divide-zinc-200 text-sm">
                        <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500">
                            <tr>
                                <th class="px-4 py-3">Trabajador</th>
                                <th class="px-4 py-3">Asist.</th>
                                <th class="px-4 py-3">Faltas</th>
                                <th class="px-4 py-3">Ordinario</th>
                                <th class="px-4 py-3">Extra</th>
                                <th class="px-4 py-3">Doble</th>
                                <th class="px-4 py-3">Triple</th>
                                <th class="px-4 py-3">Domingos</th>
                                <th class="px-4 py-3">Desc. oblig.</th>
                                <th class="px-4 py-3">Incidencias</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach (($selectedPeriod->report_summary['workers'] ?? []) as $workerRow)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-zinc-900">{{ $workerRow['employee_code'] }} - {{ $workerRow['full_name'] }}</div>
                                        <div class="text-xs text-zinc-500">{{ $workerRow['center_name'] }}{{ filled($workerRow['organizational_unit_name'] ?? null) ? ' / '.$workerRow['organizational_unit_name'] : '' }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $workerRow['attendances'] }}</td>
                                    <td class="px-4 py-3">{{ $workerRow['absences'] }}</td>
                                    <td class="px-4 py-3">{{ $this->minutesLabel((int) $workerRow['ordinary_minutes']) }}</td>
                                    <td class="px-4 py-3">{{ $this->minutesLabel((int) $workerRow['overtime_minutes']) }}</td>
                                    <td class="px-4 py-3">{{ $this->minutesLabel((int) ($workerRow['overtime_double_minutes'] ?? 0)) }}</td>
                                    <td class="px-4 py-3">{{ $this->minutesLabel((int) ($workerRow['overtime_triple_minutes'] ?? 0)) }}</td>
                                    <td class="px-4 py-3">{{ $this->minutesLabel((int) $workerRow['sunday_minutes']) }}</td>
                                    <td class="px-4 py-3">{{ $this->minutesLabel((int) $workerRow['mandatory_rest_minutes']) }}</td>
                                    <td class="px-4 py-3">{{ $workerRow['open_incidents'] }} abiertas / {{ $workerRow['closed_incidents'] }} cerradas</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="text-xs text-zinc-500">
                    Snapshot SHA-256: {{ $selectedPeriod->snapshot_sha256 }}
                </div>
            @endif
        </section>
    @endif

    <x-side-panel
        wire:model="showCreatePanel"
        title="Nuevo periodo de asistencia"
        subheading="Selecciona el centro, alcance y rango que se entregara despues a nomina o integraciones."
        labelledby="attendance-period-form-title"
        max-width="max-w-xl"
    >
        <form wire:submit="create" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-5 p-4 sm:p-6">
                <x-ui.panel-section title="Alcance del periodo" tone="blue">
                    <flux:select label="Centro" wire:model.live="form.center_id">
                        <flux:select.option value="">Selecciona centro</flux:select.option>
                        @foreach ($centers as $center)
                            <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select label="Alcance" wire:model.live="form.scope_mode">
                        <flux:select.option value="center">Todo el centro</flux:select.option>
                        <flux:select.option value="units">Unidades seleccionadas</flux:select.option>
                    </flux:select>

                    @if (($form['scope_mode'] ?? 'center') === 'units')
                        <div class="space-y-2 rounded-md border border-zinc-200 p-3">
                            <div class="text-sm font-medium text-zinc-800">Unidades</div>
                            @forelse ($availableUnits as $unit)
                                <label class="flex items-center gap-2 text-sm text-zinc-700">
                                    <input type="checkbox" value="{{ $unit->id }}" wire:model="form.organizational_unit_ids" class="rounded border-zinc-300">
                                    <span>{{ $unit->code }} - {{ $unit->name }}</span>
                                </label>
                            @empty
                                <p class="text-sm text-zinc-500">Este centro no tiene unidades activas.</p>
                            @endforelse
                            @error('form.organizational_unit_ids')
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input type="date" label="Fecha inicial" wire:model="form.period_start" />
                        <flux:input type="date" label="Fecha final" wire:model="form.period_end" />
                    </div>

                    <flux:input label="Nombre opcional" placeholder="Ej. Semana operativos agosto" wire:model="form.name" />
                    <flux:textarea label="Notas" rows="3" wire:model="form.notes" />

                    <div class="rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800">
                        Este periodo solo agrupa asistencia. Si hay bloqueantes, se revisan y dictaminan desde Jornadas.
                    </div>
                </x-ui.panel-section>
            </div>

            <div class="flex shrink-0 flex-wrap justify-end gap-3 border-t border-surface-line bg-white px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                <button type="button" class="btn-ghost" wire:click="closeCreatePanel">Cancelar</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="create">
                    <span wire:loading wire:target="create" class="btn-spinner"></span>
                    <span wire:loading.remove wire:target="create">Generar periodo</span>
                    <span wire:loading wire:target="create">Generando</span>
                </button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel
        wire:model="showCancelPanel"
        title="Cancelar periodo"
        subheading="Solo se cancelan periodos abiertos creados por error."
        labelledby="attendance-period-cancel-title"
        max-width="max-w-lg"
    >
        <form wire:submit="cancel" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-5 p-4 sm:p-6">
                <x-ui.panel-section title="Motivo de cancelación" tone="blue">
                    <flux:textarea label="Motivo" rows="4" wire:model="cancelForm.reason" required />
                </x-ui.panel-section>
            </div>
            <div class="flex shrink-0 flex-wrap justify-end gap-3 border-t border-surface-line bg-white px-5 py-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:button type="button" variant="ghost" wire:click="closeCancelPanel">Volver</flux:button>
                <flux:button type="submit" variant="danger">Cancelar periodo</flux:button>
            </div>
        </form>
    </x-side-panel>
    <x-side-panel wire:model="showExportPanel" title="Exportar período" subheading="Descarga el archivo con el layout predeterminado de la empresa." labelledby="attendance-period-export-title" max-width="max-w-xl">
        <form wire:submit="downloadLayout" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-5 p-4 sm:p-6">
                @if ($exportingPeriod)
                    <x-ui.panel-section title="Período cerrado" tone="blue">
                        <p class="font-semibold">{{ $exportingPeriod->name }}</p>
                        <p class="text-sm text-surface-muted">{{ $exportingPeriod->period_start->format('d/m/Y') }} — {{ $exportingPeriod->period_end->format('d/m/Y') }}</p>
                        <p class="text-sm text-surface-muted">Se exporta la información conservada al cierre.</p>
                    </x-ui.panel-section>
                @endif
                @if (! $defaultExportLayout)
                    <p class="text-sm text-amber-800">Define un layout activo como predeterminado antes de exportar.</p>
                    <a href="{{ route('export-layouts.index') }}" wire:navigate class="text-sm font-semibold text-brand-blue underline">Ir a Layouts de exportación</a>
                @endif
                @foreach ($errors->get('export') as $message)<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@endforeach
            </div>
            <div class="flex justify-end gap-3 border-t border-surface-line p-4">
                <button type="button" class="btn-ghost" wire:click="$set('showExportPanel', false)">Cerrar</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="downloadLayout" @disabled(! $defaultExportLayout)>Descargar con layout</button>
            </div>
        </form>
    </x-side-panel>
</section>
