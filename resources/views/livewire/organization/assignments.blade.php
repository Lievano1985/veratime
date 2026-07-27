<?php

use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\AssignTemporarySupportAction;
use App\Domains\Organization\Actions\EndTemporarySupportAction;
use App\Domains\Organization\Actions\ReplacePrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\ResolveEmploymentUnitsForDateAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use App\Models\Worker;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public array $primaryForm = [];
    public array $supportForm = [];
    public array $endForm = [];
    public array $filters = [];
    public bool $showPrimaryPanel = false;
    public bool $showSupportPanel = false;
    public bool $showEndPanel = false;
    public ?int $endingSupportId = null;

    public function mount(): void
    {
        $this->primaryForm = $this->emptyPrimaryForm();
        $this->supportForm = $this->emptySupportForm();
        $this->endForm = $this->emptyEndForm();
        $this->filters = ['center_id' => '', 'organizational_unit_id' => '', 'search' => '', 'status' => 'all'];
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.')) {
            if ($property === 'filters.center_id') {
                $this->filters['organizational_unit_id'] = '';
            }

            $this->resetPage();
        }

        if ($property === 'primaryForm.worker_ids') {
            $this->primaryForm['organizational_unit_id'] = '';
            $this->resetValidation(['primaryForm.worker_ids', 'primaryForm.organizational_unit_id']);
        }

        if (in_array($property, ['primaryForm.operation', 'primaryForm.organizational_unit_id', 'primaryForm.effective_from', 'primaryForm.reason'], true)) {
            $this->resetValidation(['primaryForm.organizational_unit_id', 'primaryForm.reason']);
        }

        if ($property === 'supportForm.support_center_id') {
            $this->supportForm['organizational_unit_id'] = '';
            $this->resetValidation(['supportForm.support_center_id', 'supportForm.organizational_unit_id']);
        }

        if (in_array($property, ['supportForm.worker_ids', 'supportForm.organizational_unit_id', 'supportForm.effective_from', 'supportForm.effective_to', 'supportForm.reason'], true)) {
            $this->resetValidation(['supportForm.worker_ids', 'supportForm.organizational_unit_id', 'supportForm.reason']);
        }
    }

    public function openPrimaryPanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('create', [EmploymentUnitAssignment::class, $company]);

        $this->primaryForm = $this->emptyPrimaryForm();
        $this->showPrimaryPanel = true;
    }

    public function openSupportPanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('create', [EmploymentUnitAssignment::class, $company]);

        $this->supportForm = $this->emptySupportForm();
        $this->showSupportPanel = true;
    }

    public function savePrimary(
        CurrentCompany $currentCompany,
        AssignPrimaryOrganizationalUnitAction $assignAction,
        ReplacePrimaryOrganizationalUnitAction $replaceAction,
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('create', [EmploymentUnitAssignment::class, $company]);

        $validated = $this->validate([
            'primaryForm.worker_ids' => ['required', 'array', 'min:1'],
            'primaryForm.worker_ids.*' => [
                'required',
                'integer',
                Rule::exists('workers', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'primaryForm.organizational_unit_id' => [
                'required',
                'integer',
                Rule::exists('organizational_units', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'primaryForm.operation' => ['required', Rule::in(['assign', 'replace'])],
            'primaryForm.effective_from' => ['required', 'date'],
            'primaryForm.reason' => ['required_if:primaryForm.operation,replace', 'nullable', 'string', 'max:1000'],
        ])['primaryForm'];

        $unit = $company->organizationalUnits()->whereKey((int) $validated['organizational_unit_id'])->firstOrFail();

        try {
            $data = [
                'effective_from' => $validated['effective_from'],
                'source' => 'manual',
                'reason' => $validated['reason'] ?? null,
                'created_by' => auth()->id(),
            ];

            DB::transaction(function () use ($company, $validated, $unit, $data, $replaceAction, $assignAction): void {
                foreach ($validated['worker_ids'] as $workerId) {
                    $relationship = $this->activeRelationshipForWorker($company, (int) $workerId, date: $validated['effective_from']);

                    $validated['operation'] === 'replace'
                        ? $replaceAction->handle($company, $relationship, $unit, $data)
                        : $assignAction->handle($company, $relationship, $unit, $data);
                }
            });
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['primaryForm.organizational_unit_id' => $exception->getMessage()]);
        }

        $this->showPrimaryPanel = false;
        $this->primaryForm = $this->emptyPrimaryForm();
        $this->resetPage();
        $count = count($validated['worker_ids']);
        Session::flash('status', $count === 1 ? 'Unidad principal guardada.' : "Unidad principal guardada para {$count} trabajadores.");
    }

    public function saveSupport(CurrentCompany $currentCompany, AssignTemporarySupportAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('create', [EmploymentUnitAssignment::class, $company]);

        $validated = $this->validate([
            'supportForm.worker_ids' => ['required', 'array', 'min:1'],
            'supportForm.worker_ids.*' => [
                'required',
                'integer',
                Rule::exists('workers', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'supportForm.support_center_id' => [
                'required',
                'integer',
                Rule::exists('centers', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'supportForm.organizational_unit_id' => [
                'required',
                'integer',
                Rule::exists('organizational_units', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'supportForm.effective_from' => ['required', 'date'],
            'supportForm.effective_to' => ['required', 'date', 'after_or_equal:supportForm.effective_from'],
            'supportForm.reason' => ['required', 'string', 'max:1000'],
        ])['supportForm'];

        $unit = $company->organizationalUnits()
            ->where('center_id', (int) $validated['support_center_id'])
            ->whereKey((int) $validated['organizational_unit_id'])
            ->firstOrFail();

        try {
            $data = [
                'effective_from' => $validated['effective_from'],
                'effective_to' => $validated['effective_to'],
                'source' => 'manual',
                'reason' => $validated['reason'],
                'created_by' => auth()->id(),
            ];

            DB::transaction(function () use ($company, $validated, $unit, $data, $action): void {
                foreach ($validated['worker_ids'] as $workerId) {
                    $relationship = $this->activeRelationshipForWorker($company, (int) $workerId, date: $validated['effective_from']);

                    $action->handle($company, $relationship, $unit, $data);
                }
            });
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['supportForm.organizational_unit_id' => $exception->getMessage()]);
        }

        $this->showSupportPanel = false;
        $this->supportForm = $this->emptySupportForm();
        $this->resetPage();
        $count = count($validated['worker_ids']);
        Session::flash('status', $count === 1 ? 'Apoyo temporal guardado.' : "Apoyo temporal guardado para {$count} trabajadores.");
    }

    public function openEndSupportPanel(int $assignmentId, CurrentCompany $currentCompany): void
    {
        $assignment = $this->authorizedAssignment($assignmentId, $currentCompany);

        abort_unless($assignment->assignment_type === 'temporary_support', 404);

        $this->endingSupportId = $assignment->id;
        $this->endForm = $this->emptyEndForm();
        $this->showEndPanel = true;
    }

    public function endSupport(CurrentCompany $currentCompany, EndTemporarySupportAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $assignment = $this->authorizedAssignment($this->endingSupportId ?? 0, $currentCompany);

        Gate::authorize('end', $assignment);

        $validated = $this->validate([
            'endForm.effective_to' => ['required', 'date'],
            'endForm.reason' => ['required', 'string', 'max:1000'],
        ])['endForm'];

        try {
            $action->handle($company, $assignment, $validated['effective_to'], $validated['reason']);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['endForm.effective_to' => $exception->getMessage()]);
        }

        $this->showEndPanel = false;
        $this->endingSupportId = null;
        $this->endForm = $this->emptyEndForm();
        $this->resetPage();
        Session::flash('status', 'Apoyo temporal finalizado.');
    }

    public function closePanels(): void
    {
        $this->showPrimaryPanel = false;
        $this->showSupportPanel = false;
        $this->showEndPanel = false;
        $this->endingSupportId = null;
        $this->resetValidation();
    }

    public function with(CurrentCompany $currentCompany, ResolveEmploymentUnitsForDateAction $resolver): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('viewAny', [EmploymentUnitAssignment::class, $company]);

        $selectedPrimaryWorker = $this->selectedWorker($company, $this->primaryForm['worker_ids'] ?? []);

        return [
            'currentCompany' => $company,
            'centers' => $company->centers()->where('status', 'active')->orderBy('name')->get(),
            'organizationalUnits' => $this->organizationalUnitFilterOptions($company),
            'assignments' => $this->assignmentQuery($company)->paginate(12),
            'primaryUnits' => $this->primaryUnitOptions($company),
            'primaryUnitHelp' => $this->primaryUnitHelp($company),
            'supportUnits' => $this->supportUnitOptions($company),
            'selectedSummary' => $this->selectedSummary($company, $selectedPrimaryWorker, $resolver),
        ];
    }

    private function assignmentQuery($company)
    {
        $search = trim((string) ($this->filters['search'] ?? ''));
        $centerId = trim((string) ($this->filters['center_id'] ?? ''));
        $unitId = trim((string) ($this->filters['organizational_unit_id'] ?? ''));
        $status = trim((string) ($this->filters['status'] ?? 'all'));

        return $company->employmentUnitAssignments()
            ->with(['employmentRelationship.worker', 'employmentRelationship.center', 'organizationalUnit.center', 'replacedBy'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($unitId !== '', fn ($query) => $query->where('organizational_unit_id', (int) $unitId))
            ->when($centerId !== '', function ($query) use ($centerId): void {
                $query->whereHas('employmentRelationship', fn ($relationshipQuery) => $relationshipQuery->where('center_id', (int) $centerId));
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('employmentRelationship.worker', function ($workerQuery) use ($search): void {
                    $workerQuery->where('employee_code', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id');
    }

    private function organizationalUnitFilterOptions($company)
    {
        $centerId = trim((string) ($this->filters['center_id'] ?? ''));

        return $company->organizationalUnits()
            ->where('status', 'active')
            ->when($centerId !== '', fn ($query) => $query->where('center_id', (int) $centerId))
            ->orderBy('name')
            ->get();
    }

    private function selectedSummary($company, ?Worker $worker, ResolveEmploymentUnitsForDateAction $resolver): ?array
    {
        if (! $worker) {
            return null;
        }

        $relationship = $this->activeRelationshipForWorker($company, $worker->id, fail: false);
        if (! $relationship) {
            return ['worker' => $worker, 'relationship' => null, 'resolved' => null];
        }

        return [
            'worker' => $worker,
            'relationship' => $relationship,
            'resolved' => $resolver->handle($company, $relationship, now()->toDateString()),
        ];
    }

    private function primaryUnitOptions($company)
    {
        $centerIds = $this->selectedPrimaryCenterIds($company);

        return $company->organizationalUnits()
            ->where('status', 'active')
            ->when($centerIds !== null, fn ($query) => $query->whereIn('center_id', $centerIds))
            ->orderBy('name')
            ->get();
    }

    private function selectedPrimaryCenterIds($company): ?array
    {
        $workerIds = collect($this->primaryForm['worker_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($workerIds->isEmpty()) {
            return null;
        }

        $date = filled($this->primaryForm['effective_from'] ?? null)
            ? (string) $this->primaryForm['effective_from']
            : now()->toDateString();

        $relationships = EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->whereIn('worker_id', $workerIds)
            ->where('status', 'active')
            ->whereDate('started_at', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $date);
            })
            ->get(['worker_id', 'center_id']);

        $centerIds = $relationships
            ->pluck('center_id')
            ->unique()
            ->values();

        if ($relationships->pluck('worker_id')->unique()->count() !== $workerIds->count() || $centerIds->count() !== 1) {
            return [];
        }

        return $centerIds->all();
    }

    private function primaryUnitHelp($company): ?string
    {
        $workerIds = collect($this->primaryForm['worker_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($workerIds->isEmpty()) {
            return 'Primero selecciona trabajadores; se mostraran las unidades compatibles con su centro.';
        }

        $centerIds = $this->selectedPrimaryCenterIds($company);

        if ($centerIds === []) {
            return 'Selecciona trabajadores con relacion laboral vigente en el mismo centro para asignar una unidad principal.';
        }

        return 'Solo se muestran unidades activas del centro de los trabajadores seleccionados.';
    }

    private function supportUnitOptions($company)
    {
        $centerId = (int) ($this->supportForm['support_center_id'] ?? 0);

        return $company->organizationalUnits()
            ->where('status', 'active')
            ->when($centerId > 0, fn ($query) => $query->where('center_id', $centerId))
            ->orderBy('name')
            ->get();
    }

    private function selectedWorker($company, array $workerIds): ?Worker
    {
        $workerId = (int) collect($workerIds)->first();

        if ($workerId <= 0) {
            return null;
        }

        return $company->workers()->where('status', 'active')->whereKey($workerId)->first();
    }

    private function activeRelationshipForWorker($company, int $workerId, bool $fail = true, ?string $date = null): ?EmploymentRelationship
    {
        $date ??= now()->toDateString();

        $query = EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $workerId)
            ->where('status', 'active')
            ->whereDate('started_at', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $date);
            })
            ->latest('started_at');

        return $fail ? $query->firstOrFail() : $query->first();
    }

    private function authorizedAssignment(int $assignmentId, CurrentCompany $currentCompany): EmploymentUnitAssignment
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $assignment = $company->employmentUnitAssignments()->whereKey($assignmentId)->firstOrFail();

        Gate::authorize('update', $assignment);

        return $assignment;
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function emptyPrimaryForm(): array
    {
        return [
            'worker_ids' => [],
            'organizational_unit_id' => '',
            'operation' => 'replace',
            'effective_from' => now()->toDateString(),
            'reason' => '',
        ];
    }

    private function emptySupportForm(): array
    {
        return [
            'worker_ids' => [],
            'support_center_id' => '',
            'organizational_unit_id' => '',
            'effective_from' => now()->toDateString(),
            'effective_to' => now()->addWeek()->toDateString(),
            'reason' => '',
        ];
    }

    private function emptyEndForm(): array
    {
        return [
            'effective_to' => now()->toDateString(),
            'reason' => '',
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <flux:heading size="xl">Asignaciones organizacionales</flux:heading>
            <flux:subheading>Administra unidad principal, apoyos temporales e historial no destructivo.</flux:subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button type="button" variant="primary" wire:click="openPrimaryPanel">
                Unidad principal
            </flux:button>
            <flux:button type="button" wire:click="openSupportPanel">
                Apoyo temporal
            </flux:button>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    @if ($selectedSummary)
        <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading>Trabajador seleccionado</flux:heading>
            <div class="mt-3 grid gap-4 md:grid-cols-3">
                <div>
                    <p class="text-xs uppercase text-zinc-500">Trabajador</p>
                    <p class="font-medium">{{ $selectedSummary['worker']->employee_code }} - {{ $selectedSummary['worker']->full_name }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-zinc-500">Centro</p>
                    <p class="font-medium">{{ $selectedSummary['relationship']?->center?->name ?? 'Sin relacion vigente' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-zinc-500">Unidad principal vigente</p>
                    <p class="font-medium">{{ $selectedSummary['resolved']['primary']?->name ?? 'Sin unidad principal' }}</p>
                </div>
            </div>
        </section>
    @endif

    <section class="space-y-4">
        <div class="grid gap-4 rounded-md border border-zinc-200 p-4 dark:border-zinc-700 md:grid-cols-4">
            <flux:input label="Buscar trabajador" placeholder="Clave o nombre" wire:model.live.debounce.350ms="filters.search" />
            <flux:select label="Centro" wire:model.live="filters.center_id">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select label="Unidad" wire:model.live="filters.organizational_unit_id">
                <flux:select.option value="">Todas</flux:select.option>
                @foreach ($organizationalUnits as $unit)
                    <flux:select.option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select label="Estado" wire:model.live="filters.status">
                <flux:select.option value="all">Todos</flux:select.option>
                <flux:select.option value="active">Vigentes</flux:select.option>
                <flux:select.option value="inactive">Finalizados</flux:select.option>
                <flux:select.option value="replaced">Reemplazados</flux:select.option>
            </flux:select>
        </div>

        <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
            <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">Trabajador</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Unidad</th>
                        <th class="px-4 py-3">Vigencia</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($assignments as $assignment)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ $assignment->employmentRelationship?->worker?->full_name }}</span>
                                <span class="text-xs text-zinc-500">{{ $assignment->employmentRelationship?->worker?->employee_code }} - {{ $assignment->employmentRelationship?->center?->name }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $assignment->assignment_type === 'primary' ? 'Principal' : 'Apoyo temporal' }}</td>
                            <td class="px-4 py-3">{{ $assignment->organizationalUnit?->name }} <span class="text-xs text-zinc-500">({{ $assignment->organizationalUnit?->center?->name }})</span></td>
                            <td class="px-4 py-3">{{ $assignment->effective_from?->toDateString() }} - {{ $assignment->effective_to?->toDateString() ?? 'Abierta' }}</td>
                            <td class="px-4 py-3">{{ $assignment->status === 'active' ? 'Vigente' : ($assignment->status === 'inactive' ? 'Finalizado' : 'Reemplazado') }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($assignment->assignment_type === 'temporary_support' && $assignment->status === 'active')
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="openEndSupportPanel({{ $assignment->id }})">
                                        Finalizar
                                    </flux:button>
                                @else
                                    <span class="text-xs text-zinc-500">Historial</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
                                No hay asignaciones organizacionales que coincidan con los filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $assignments->links() }}
    </section>

    <x-side-panel wire:model="showPrimaryPanel" title="Unidad principal" subheading="Asigna o reemplaza la unidad principal vigente." labelledby="primary-unit-form-title">
        <form wire:submit="savePrimary" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <livewire:workers.multi-select wire:model.live="primaryForm.worker_ids" heading="Trabajadores" subheading="Selecciona uno o varios trabajadores activos." :result-limit="150" :show-primary-assignment-status="true" :assignment-date="$primaryForm['effective_from']" />

                <flux:select label="Operacion" wire:model="primaryForm.operation">
                    <flux:select.option value="replace">Asignar o reemplazar unidad vigente</flux:select.option>
                    <flux:select.option value="assign">Asignar solo si no tiene unidad vigente</flux:select.option>
                </flux:select>

                <flux:select label="Unidad" wire:model="primaryForm.organizational_unit_id">
                    <flux:select.option value="">Selecciona una unidad</flux:select.option>
                    @foreach ($primaryUnits as $unit)
                        <flux:select.option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($primaryUnitHelp)
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $primaryUnitHelp }}</p>
                @endif

                <flux:input type="date" label="Vigente desde" wire:model="primaryForm.effective_from" />
                <flux:textarea label="Motivo" wire:model="primaryForm.reason" placeholder="Requerido al reemplazar." />

                @error('primaryForm.organizational_unit_id')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="closePanels">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showSupportPanel" title="Apoyo temporal" subheading="El apoyo puede ser en otro centro de la misma empresa." labelledby="support-unit-form-title">
        <form wire:submit="saveSupport" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <livewire:workers.multi-select wire:model.live="supportForm.worker_ids" heading="Trabajadores" subheading="Selecciona uno o varios trabajadores activos." :result-limit="150" />

                <flux:select label="Centro de apoyo" wire:model.live="supportForm.support_center_id">
                    <flux:select.option value="">Selecciona un centro</flux:select.option>
                    @foreach ($centers as $center)
                        <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select label="Unidad de apoyo" wire:model="supportForm.organizational_unit_id">
                    <flux:select.option value="">Selecciona una unidad</flux:select.option>
                    @foreach ($supportUnits as $unit)
                        <flux:select.option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input type="date" label="Desde" wire:model="supportForm.effective_from" />
                    <flux:input type="date" label="Hasta" wire:model="supportForm.effective_to" />
                </div>

                <flux:textarea label="Motivo" wire:model="supportForm.reason" required />

                @error('supportForm.organizational_unit_id')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="closePanels">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Guardar apoyo</flux:button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showEndPanel" title="Finalizar apoyo" subheading="Cierra la vigencia sin borrar el historial." labelledby="end-support-form-title" max-width="max-w-md">
        <form wire:submit="endSupport" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <flux:input type="date" label="Finaliza el" wire:model="endForm.effective_to" />
                <flux:textarea label="Motivo" wire:model="endForm.reason" required />
                @error('endForm.effective_to')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                <flux:button type="button" variant="ghost" wire:click="closePanels">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Finalizar</flux:button>
            </div>
        </form>
    </x-side-panel>
</section>
