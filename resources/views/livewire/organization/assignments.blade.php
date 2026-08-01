<?php

use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\ReplacePrimaryOrganizationalUnitAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
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
        Gate::authorize('create', [EmploymentUnitAssignment::class, $company]);

        $this->primaryForm = $this->emptyPrimaryForm();
        $this->showPrimaryPanel = true;
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

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        Gate::authorize('viewAny', [EmploymentUnitAssignment::class, $company]);

        return [
            'currentCompany' => $company,
            'centers' => $company->centers()->where('status', 'active')->orderBy('name')->get(),
            'organizationalUnits' => $this->organizationalUnitFilterOptions($company),
            'assignments' => $this->assignmentQuery($company)->paginate(12),
            'primaryUnits' => $this->primaryUnitOptions($company),
            'primaryUnitHelp' => $this->primaryUnitHelp($company),
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
            ->orderByDesc('updated_at')
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
            return 'Primero selecciona trabajadores; se mostraran las unidades compatibles con su centro.';
        }

        $centerIds = $this->selectedPrimaryCenterIds($company);

        if ($centerIds === []) {
            return 'Selecciona trabajadores activos con relacion laboral activa en el mismo centro para asignar una unidad principal.';
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
            throw new \InvalidArgumentException('No hay una relacion laboral activa para el trabajador seleccionado.');
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

<section class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
            <flux:heading size="xl">Asignaciones organizacionales</flux:heading>
            <flux:subheading>Administra la segmentacion operativa actual de los trabajadores.</flux:subheading>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button type="button" variant="primary" wire:click="openPrimaryPanel">
                Cambiar unidad
            </flux:button>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <section class="space-y-4">
        <div class="grid gap-4 rounded-md border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/60 md:grid-cols-4">
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
                        <th class="px-4 py-3">Segmentacion</th>
                        <th class="px-4 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                    @forelse ($assignments as $assignment)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="flex flex-wrap items-center gap-2 font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $assignment->employmentRelationship?->worker?->full_name }}
                                    @if ($this->workerStatusLabel($assignment->employmentRelationship?->worker?->status))
                                        <x-ui.badge variant="{{ $this->workerStatusBadgeVariant($assignment->employmentRelationship?->worker?->status) }}">
                                            {{ $this->workerStatusLabel($assignment->employmentRelationship?->worker?->status) }}
                                        </x-ui.badge>
                                    @endif
                                </span>
                                <span class="text-xs text-zinc-500">{{ $assignment->employmentRelationship?->worker?->employee_code }} - {{ $assignment->employmentRelationship?->center?->name }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $assignment->assignment_type === 'primary' ? 'Principal' : 'Apoyo historico' }}</td>
                            <td class="px-4 py-3">{{ $assignment->organizationalUnit?->name }} <span class="text-xs text-zinc-500">({{ $assignment->organizationalUnit?->center?->name }})</span></td>
                            <td class="px-4 py-3">
                                <span class="text-zinc-700 dark:text-zinc-200">Actual</span>
                                <span class="block text-xs text-zinc-500">La vigencia depende del alta o baja del trabajador.</span>
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge variant="{{ $this->assignmentStatusBadgeVariant($assignment->status) }}">
                                    {{ $this->assignmentStatusLabel($assignment->status) }}
                                </x-ui.badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-500">
                                No hay asignaciones organizacionales que coincidan con los filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $assignments->links() }}
    </section>

    <x-side-panel wire:model="showPrimaryPanel" title="Unidad principal" subheading="Cambia la segmentacion actual del trabajador." labelledby="primary-unit-form-title">
        <form wire:submit="savePrimary" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <livewire:workers.multi-select wire:model.live="primaryForm.worker_ids" heading="Trabajadores" subheading="Selecciona uno o varios trabajadores activos." :result-limit="150" :show-primary-assignment-status="true" />

                <flux:select label="Operacion" wire:model="primaryForm.operation">
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
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $primaryUnitHelp }}</p>
                @endif

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
</section>
