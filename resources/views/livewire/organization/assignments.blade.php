<?php

use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\ReplacePrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\ResolveUserOperationalScopeAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\Worker;
use App\Support\RoleKey;
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
    public array $filters = [];
    public bool $showPrimaryPanel = false;

    public function mount(): void
    {
        $this->primaryForm = $this->emptyPrimaryForm();
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

        if (in_array($property, ['primaryForm.operation', 'primaryForm.organizational_unit_id', 'primaryForm.reason'], true)) {
            $this->resetValidation(['primaryForm.organizational_unit_id', 'primaryForm.reason']);
        }
    }

    public function openPrimaryPanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('viewAny', [EmploymentUnitAssignment::class, $company]);

        $this->primaryForm = $this->emptyPrimaryForm();
        $this->showPrimaryPanel = true;
    }

    public function savePrimary(
        CurrentCompany $currentCompany,
        AssignPrimaryOrganizationalUnitAction $assignAction,
        ReplacePrimaryOrganizationalUnitAction $replaceAction,
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('viewAny', [EmploymentUnitAssignment::class, $company]);

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
            'primaryForm.reason' => ['required_if:primaryForm.operation,replace', 'nullable', 'string', 'max:1000'],
        ])['primaryForm'];

        $unit = $company->organizationalUnits()->whereKey((int) $validated['organizational_unit_id'])->firstOrFail();

        try {
            $data = [
                'source' => 'manual',
                'reason' => $validated['reason'] ?? null,
                'created_by' => auth()->id(),
            ];

            DB::transaction(function () use ($company, $validated, $unit, $data, $replaceAction, $assignAction): void {
                foreach ($validated['worker_ids'] as $workerId) {
                    $relationship = $this->activeRelationshipForWorker($company, (int) $workerId);
                    Gate::authorize('assignToUnit', [EmploymentUnitAssignment::class, $company, $relationship, $unit]);

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

    public function closePanels(): void
    {
        $this->showPrimaryPanel = false;
        $this->resetValidation();
    }

    public function with(CurrentCompany $currentCompany, ResolveUserOperationalScopeAction $resolveUserScope): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('viewAny', [EmploymentUnitAssignment::class, $company]);
        $scope = in_array(auth()->user()->roleKeyForCompany($company), RoleKey::scopedOperators(), true)
            ? $resolveUserScope->handle($company, auth()->user(), now()->toDateString())
            : null;

        return [
            'currentCompany' => $company,
            'centers' => $this->centerOptions($company, $scope),
            'organizationalUnits' => $this->organizationalUnitFilterOptions($company, $scope),
            'assignments' => $this->assignmentQuery($company, $scope)->paginate(12),
            'primaryUnits' => $this->primaryUnitOptions($company, $scope),
            'primaryUnitHelp' => $this->primaryUnitHelp($company),
        ];
    }

    private function assignmentQuery($company, ?array $scope = null)
    {
        $search = trim((string) ($this->filters['search'] ?? ''));
        $centerId = trim((string) ($this->filters['center_id'] ?? ''));
        $unitId = trim((string) ($this->filters['organizational_unit_id'] ?? ''));
        $status = trim((string) ($this->filters['status'] ?? 'all'));

        return $company->employmentUnitAssignments()
            ->with(['employmentRelationship.worker', 'employmentRelationship.center', 'organizationalUnit.center', 'replacedBy'])
            ->when($scope !== null, function ($query) use ($scope): void {
                $query->where(function ($scopeQuery) use ($scope): void {
                    $scopeQuery
                        ->whereHas('employmentRelationship', fn ($relationshipQuery) => $relationshipQuery->whereIn('center_id', $scope['center_ids']))
                        ->orWhereIn('organizational_unit_id', $scope['organizational_unit_ids']);
                });
            })
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
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    private function centerOptions($company, ?array $scope)
    {
        return $company->centers()
            ->where('status', 'active')
            ->when($scope !== null, function ($query) use ($scope): void {
                $query->where(function ($scopeQuery) use ($scope): void {
                    $scopeQuery
                        ->whereIn('id', $scope['center_ids'])
                        ->orWhereHas('organizationalUnits', fn ($unitQuery) => $unitQuery->whereIn('id', $scope['organizational_unit_ids']));
                });
            })
            ->orderBy('name')
            ->get();
    }

    private function organizationalUnitFilterOptions($company, ?array $scope = null)
    {
        $centerId = trim((string) ($this->filters['center_id'] ?? ''));

        return $company->organizationalUnits()
            ->where('status', 'active')
            ->when($scope !== null, function ($query) use ($scope): void {
                $query->where(function ($scopeQuery) use ($scope): void {
                    $scopeQuery
                        ->whereIn('center_id', $scope['center_ids'])
                        ->orWhereIn('id', $scope['organizational_unit_ids']);
                });
            })
            ->when($centerId !== '', fn ($query) => $query->where('center_id', (int) $centerId))
            ->orderBy('name')
            ->get();
    }

    private function primaryUnitOptions($company, ?array $scope = null)
    {
        $centerIds = $this->selectedPrimaryCenterIds($company);

        return $company->organizationalUnits()
            ->where('status', 'active')
            ->when($scope !== null, function ($query) use ($scope): void {
                $query->where(function ($scopeQuery) use ($scope): void {
                    $scopeQuery
                        ->whereIn('center_id', $scope['center_ids'])
                        ->orWhereIn('id', $scope['organizational_unit_ids']);
                });
            })
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

        $relationships = EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->whereIn('worker_id', $workerIds)
            ->where('status', 'active')
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
            return 'Primero selecciona trabajadores; se mostrarán las unidades compatibles con su centro.';
        }

        $centerIds = $this->selectedPrimaryCenterIds($company);

        if ($centerIds === []) {
            return 'Selecciona trabajadores activos con relación laboral activa en el mismo centro para asignar una unidad principal.';
        }

        return 'Solo se muestran unidades activas del centro actual de los trabajadores seleccionados.';
    }

    private function assignmentStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Vigente',
            'inactive' => 'Finalizado',
            'replaced' => 'Reemplazado',
            default => ucfirst($status),
        };
    }

    private function assignmentStatusBadgeVariant(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'inactive' => 'neutral',
            'replaced' => 'warning',
            default => 'neutral',
        };
    }

    private function workerStatusLabel(?string $status): ?string
    {
        return match ($status) {
            'terminated' => 'Dado de baja',
            'inactive' => 'Inactivo',
            'suspended' => 'Suspendido',
            default => null,
        };
    }

    private function workerStatusBadgeVariant(?string $status): string
    {
        return match ($status) {
            'terminated' => 'danger',
            'suspended' => 'warning',
            default => 'neutral',
        };
    }

    private function activeRelationshipForWorker($company, int $workerId, bool $fail = true): ?EmploymentRelationship
    {
        $query = EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->where('worker_id', $workerId)
            ->where('status', 'active')
            ->latest('started_at');

        $relationship = $query->first();

        if (! $relationship && $fail) {
            throw new \InvalidArgumentException('No hay una relación laboral activa para el trabajador seleccionado.');
        }

        return $relationship;
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
            'reason' => '',
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 bg-surface-bg p-6 text-surface-text">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-brand-navy">Asignaciones organizacionales</h1>
            <p class="mt-1.5 text-[13.5px] text-surface-muted">Administra la segmentación operativa actual de los trabajadores.</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <button type="button" class="btn-primary" wire:click="openPrimaryPanel">
                <span class="text-base leading-none">+</span>
                Cambiar unidad
            </button>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-status-rest-line bg-status-rest-bg px-4 py-3 text-sm font-medium text-status-rest-text">
            {{ session('status') }}
        </div>
    @endif

    <section class="space-y-4">
        <div class="grid gap-4 rounded-2xl border border-surface-line bg-surface-card p-5 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)] sm:grid-cols-2 xl:grid-cols-4">
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

        <div class="table-wrap">
            <table class="w-full min-w-[900px] border-collapse">
                <thead>
                    <tr>
                        <th class="table-head-cell">Trabajador</th>
                        <th class="table-head-cell">Tipo</th>
                        <th class="table-head-cell">Unidad</th>
                        <th class="table-head-cell">Segmentación</th>
                        <th class="table-head-cell">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        <tr class="table-row">
                            <td class="table-cell">
                                <span class="flex flex-wrap items-center gap-2 font-semibold text-brand-navy">
                                    {{ $assignment->employmentRelationship?->worker?->full_name }}
                                    @if ($this->workerStatusLabel($assignment->employmentRelationship?->worker?->status))
                                        <x-ui.badge variant="{{ $this->workerStatusBadgeVariant($assignment->employmentRelationship?->worker?->status) }}">
                                            {{ $this->workerStatusLabel($assignment->employmentRelationship?->worker?->status) }}
                                        </x-ui.badge>
                                    @endif
                                </span>
                                <span class="text-xs text-surface-muted">{{ $assignment->employmentRelationship?->worker?->employee_code }} - {{ $assignment->employmentRelationship?->center?->name }}</span>
                            </td>
                            <td class="table-cell">{{ $assignment->assignment_type === 'primary' ? 'Principal' : 'Apoyo histórico' }}</td>
                            <td class="table-cell">{{ $assignment->organizationalUnit?->name }} <span class="text-xs text-surface-muted">({{ $assignment->organizationalUnit?->center?->name }})</span></td>
                            <td class="table-cell">
                                <span class="text-brand-navy">Actual</span>
                                <span class="block text-xs text-surface-muted">La vigencia depende del alta o baja del trabajador.</span>
                            </td>
                            <td class="table-cell">
                                <x-ui.badge variant="{{ $this->assignmentStatusBadgeVariant($assignment->status) }}">
                                    {{ $this->assignmentStatusLabel($assignment->status) }}
                                </x-ui.badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="table-cell py-8 text-center text-surface-muted">
                                No hay asignaciones organizacionales que coincidan con los filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $assignments->links() }}
    </section>

    <x-side-panel wire:model="showPrimaryPanel" title="Unidad principal" subheading="Cambia la segmentación actual del trabajador." labelledby="primary-unit-form-title">
        <form wire:submit="savePrimary" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <livewire:workers.multi-select wire:model.live="primaryForm.worker_ids" heading="Trabajadores" subheading="Selecciona uno o varios trabajadores activos." :result-limit="150" :show-primary-assignment-status="true" />

                <flux:select label="Operación" wire:model="primaryForm.operation">
                    <flux:select.option value="replace">Asignar o cambiar unidad actual</flux:select.option>
                    <flux:select.option value="assign">Asignar solo si no tiene unidad</flux:select.option>
                </flux:select>

                <flux:select label="Unidad" wire:model="primaryForm.organizational_unit_id">
                    <flux:select.option value="">Selecciona una unidad</flux:select.option>
                    @foreach ($primaryUnits as $unit)
                        <flux:select.option value="{{ $unit->id }}">{{ $unit->code }} - {{ $unit->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($primaryUnitHelp)
                    <p class="form-hint">{{ $primaryUnitHelp }}</p>
                @endif

                <flux:textarea label="Motivo" wire:model="primaryForm.reason" placeholder="Requerido al reemplazar." />

                @error('primaryForm.organizational_unit_id')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3 border-t border-surface-line p-6">
                <button type="button" class="btn-ghost" wire:click="closePanels">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar</button>
            </div>
        </form>
    </x-side-panel>
</section>
