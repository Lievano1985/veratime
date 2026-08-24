<?php

use App\Domains\Organization\Actions\CreateOrganizationalUnitAction;
use App\Domains\Organization\Actions\DeleteOrganizationalUnitIfUnusedAction;
use App\Domains\Organization\Actions\InactivateOrganizationalUnitAction;
use App\Domains\Organization\Actions\ResolveUserOperationalScopeAction;
use App\Domains\Organization\Actions\UpdateOrganizationalUnitAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\OrganizationalUnit;
use App\Support\RoleKey;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public array $form = [];
    public array $filters = [];
    public bool $showFormPanel = false;
    public ?int $editingUnitId = null;

    public function mount(): void
    {
        $this->form = $this->emptyForm();
        $this->filters = ['center_id' => '', 'status' => 'active', 'search' => ''];
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.')) {
            $this->resetPage();
        }

        if (in_array($property, ['form.center_id', 'form.type'], true)) {
            $this->form['parent_id'] = '';
        }
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [OrganizationalUnit::class, $company]);

        $this->editingUnitId = null;
        $this->form = $this->emptyForm();
        $this->showFormPanel = true;
    }

    public function loadEditForm(int $unitId, CurrentCompany $currentCompany): void
    {
        $unit = $this->authorizedUnit($unitId, $currentCompany);

        $this->editingUnitId = $unit->id;
        $this->form = [
            'center_id' => (string) $unit->center_id,
            'type' => $unit->type,
            'parent_id' => $unit->parent_id ? (string) $unit->parent_id : '',
            'code' => $unit->code,
            'name' => $unit->name,
            'status' => $unit->status,
        ];
        $this->showFormPanel = true;
    }

    public function save(
        CurrentCompany $currentCompany,
        CreateOrganizationalUnitAction $createAction,
        UpdateOrganizationalUnitAction $updateAction,
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);
        $unit = $this->editingUnitId ? $this->authorizedUnit($this->editingUnitId, $currentCompany) : null;

        $validated = $this->validate([
            'form.center_id' => [
                'required',
                'integer',
                Rule::exists('centers', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'form.type' => ['required', Rule::in(['department', 'area', 'team'])],
            'form.parent_id' => [
                'nullable',
                'integer',
                Rule::exists('organizational_units', 'id')->where('company_id', $company->id)->where('status', 'active'),
            ],
            'form.code' => ['required', 'string', 'max:50'],
            'form.name' => ['required', 'string', 'max:255'],
            'form.status' => ['required', Rule::in(['active', 'inactive'])],
        ])['form'];

        $center = $company->centers()->whereKey((int) $validated['center_id'])->where('status', 'active')->firstOrFail();
        $parent = filled($validated['parent_id'] ?? null)
            ? $company->organizationalUnits()->whereKey((int) $validated['parent_id'])->firstOrFail()
            : null;

        $unit
            ? Gate::authorize('update', $unit)
            : Gate::authorize('createInScope', [OrganizationalUnit::class, $company, $center, $parent]);

        try {
            $unit
                ? $updateAction->handle($company, $unit, $validated, $parent)
                : $createAction->handle($company, $center, $validated, $parent);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['form.parent_id' => $exception->getMessage()]);
        }

        $this->showFormPanel = false;
        $this->editingUnitId = null;
        $this->form = $this->emptyForm();
        $this->resetPage();

        Session::flash('status', $unit ? 'Unidad actualizada.' : 'Unidad creada.');
    }

    public function inactivate(int $unitId, CurrentCompany $currentCompany, InactivateOrganizationalUnitAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $unit = $this->authorizedUnit($unitId, $currentCompany);

        Gate::authorize('inactivate', $unit);

        try {
            $action->handle($company, $unit);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['unit' => $exception->getMessage()]);
        }

        Session::flash('status', 'Unidad inactivada.');
        $this->resetPage();
    }

    public function delete(int $unitId, CurrentCompany $currentCompany, DeleteOrganizationalUnitIfUnusedAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $unit = $this->authorizedUnit($unitId, $currentCompany);

        Gate::authorize('delete', $unit);

        try {
            $action->handle($company, $unit);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['unit' => $exception->getMessage()]);
        }

        Session::flash('status', 'Unidad eliminada.');
        $this->resetPage();
    }

    public function closeFormPanel(): void
    {
        $this->showFormPanel = false;
        $this->editingUnitId = null;
        $this->resetValidation('form');
    }

    public function with(CurrentCompany $currentCompany, ResolveUserOperationalScopeAction $resolveUserScope): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [OrganizationalUnit::class, $company]);
        $scope = in_array(auth()->user()->roleKeyForCompany($company), RoleKey::scopeAssignableRoles(), true)
            ? $resolveUserScope->handle($company, auth()->user(), now()->toDateString())
            : null;

        return [
            'currentCompany' => $company,
            'centers' => $this->centerQuery($company, $scope)->get(),
            'units' => $this->unitQuery($company, $scope)->paginate(12),
            'parentOptions' => $this->parentOptions($company),
            'canManageUnits' => Gate::allows('create', [OrganizationalUnit::class, $company])
                || (in_array(auth()->user()->roleKeyForCompany($company), RoleKey::scopedOperators(), true)
                    && (($scope['center_ids'] ?? []) !== [] || ($scope['organizational_unit_ids'] ?? []) !== [])),
            'visibleOrganizationalUnitIds' => $scope['organizational_unit_ids'] ?? null,
        ];
    }

    private function unitQuery($company, ?array $scope = null)
    {
        $search = trim((string) ($this->filters['search'] ?? ''));
        $centerId = trim((string) ($this->filters['center_id'] ?? ''));
        $status = trim((string) ($this->filters['status'] ?? 'active'));

        return $company->organizationalUnits()
            ->with(['center', 'parent'])
            ->when($scope !== null, function ($query) use ($scope): void {
                $query->where(function ($scopeQuery) use ($scope): void {
                    $scopeQuery
                        ->whereIn('center_id', $scope['center_ids'])
                        ->orWhereIn('id', $scope['organizational_unit_ids']);
                });
            })
            ->when($centerId !== '', fn ($query) => $query->where('center_id', (int) $centerId))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('center_id')
            ->orderByRaw("case type when 'department' then 1 when 'area' then 2 else 3 end")
            ->orderBy('name');
    }

    private function centerQuery($company, ?array $scope = null)
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
            ->orderBy('name');
    }

    private function parentOptions($company)
    {
        $centerId = (int) ($this->form['center_id'] ?? 0);
        $type = (string) ($this->form['type'] ?? '');

        if ($centerId <= 0 || $type === 'department') {
            return collect();
        }

        return $company->organizationalUnits()
            ->where('center_id', $centerId)
            ->where('status', 'active')
            ->whereIn('type', $type === 'area' ? ['department'] : ['area'])
            ->when(in_array(auth()->user()->roleKeyForCompany($company), RoleKey::scopedOperators(), true), function ($query) use ($company): void {
                $scope = app(ResolveUserOperationalScopeAction::class)->handle($company, auth()->user(), now()->toDateString());
                $query->where(function ($scopeQuery) use ($scope): void {
                    $scopeQuery
                        ->whereIn('center_id', $scope['center_ids'])
                        ->orWhereIn('id', $scope['organizational_unit_ids']);
                });
            })
            ->when($this->editingUnitId, fn ($query) => $query->whereKeyNot($this->editingUnitId))
            ->orderBy('name')
            ->get();
    }

    private function authorizedUnit(int $unitId, CurrentCompany $currentCompany): OrganizationalUnit
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $unit = $company->organizationalUnits()->with(['company', 'center', 'parent'])->whereKey($unitId)->firstOrFail();

        Gate::authorize('update', $unit);

        return $unit;
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function emptyForm(): array
    {
        return [
            'center_id' => '',
            'type' => 'department',
            'parent_id' => '',
            'code' => '',
            'name' => '',
            'status' => 'active',
        ];
    }
}; ?>

<section class="flex h-full w-full flex-1 flex-col gap-6 bg-surface-bg p-6 text-surface-text">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-brand-navy">Areas y departamentos</h1>
            <p class="mt-1.5 text-[13.5px] text-surface-muted">Administra departamentos, areas y equipos por centro.</p>
        </div>

        @if ($canManageUnits)
            <button type="button" class="btn-primary" wire:click="openCreatePanel">
                <span class="text-base leading-none">+</span>
                Nueva unidad
            </button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-status-rest-line bg-status-rest-bg px-4 py-3 text-sm font-medium text-status-rest-text">
            {{ session('status') }}
        </div>
    @endif

    @error('unit')
        <div class="rounded-xl border border-status-pending-line bg-status-pending-bg px-4 py-3 text-sm font-medium text-status-pending-text">
            {{ $message }}
        </div>
    @enderror

    <section class="rounded-2xl border border-surface-line bg-surface-card p-6 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
        <div class="grid gap-4 md:grid-cols-3">
            <flux:input label="Buscar" placeholder="Codigo o nombre" wire:model.live.debounce.350ms="filters.search" />

            <flux:select label="Centro" wire:model.live="filters.center_id">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($centers as $center)
                    <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select label="Estado" wire:model.live="filters.status">
                <flux:select.option value="active">Activas</flux:select.option>
                <flux:select.option value="inactive">Inactivas</flux:select.option>
                <flux:select.option value="all">Todas</flux:select.option>
            </flux:select>
        </div>

        <div class="table-wrap mt-5">
            <table class="w-full border-collapse">
                <colgroup>
                    <col class="w-[26%]">
                    <col class="w-[14%]">
                    <col class="w-[20%]">
                    <col class="w-[18%]">
                    <col class="w-[10%]">
                    <col class="w-[12%]">
                </colgroup>
                <thead>
                    <tr>
                        <th class="table-head-cell">Unidad</th>
                        <th class="table-head-cell">Tipo</th>
                        <th class="table-head-cell">Centro</th>
                        <th class="table-head-cell">Padre</th>
                        <th class="table-head-cell">Estado</th>
                        <th class="table-head-cell text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($units as $unit)
                        <tr class="table-row">
                            <td class="table-cell">
                                <span class="block font-semibold text-brand-navy">{{ $unit->code }} - {{ $unit->name }}</span>
                                <span class="text-xs text-surface-muted">{{ $unit->type === 'department' ? 'Departamento' : ($unit->type === 'area' ? 'Area' : 'Equipo') }}</span>
                            </td>
                            <td class="table-cell">
                                <span class="badge-muted">
                                    {{ $unit->type === 'department' ? 'Departamento' : ($unit->type === 'area' ? 'Area' : 'Equipo') }}
                                </span>
                            </td>
                            <td class="table-cell text-surface-muted">{{ $unit->center?->name }}</td>
                            <td class="table-cell text-surface-muted">
                                @if (! $unit->parent_id)
                                    Centro directo
                                @elseif ($visibleOrganizationalUnitIds === null || in_array($unit->parent_id, $visibleOrganizationalUnitIds, true))
                                    {{ $unit->parent?->name }}
                                @else
                                    Fuera del alcance
                                @endif
                            </td>
                            <td class="table-cell">
                                <span class="{{ $unit->status === 'active' ? 'badge-success' : 'badge-muted' }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                    {{ $unit->status === 'active' ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>
                            <td class="table-cell">
                                @if ($canManageUnits)
                                    <div class="flex justify-end gap-1.5">
                                        <button type="button" class="btn-icon" wire:click="loadEditForm({{ $unit->id }})" aria-label="Editar unidad" title="Editar">
                                            <svg class="h-4 w-4 text-surface-muted" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                        @if ($unit->status === 'active')
                                            <button type="button" class="btn-icon" wire:confirm="Esta accion inactivara la unidad si no tiene hijos, asignaciones o alcances vigentes." wire:click="inactivate({{ $unit->id }})" aria-label="Inactivar unidad" title="Inactivar">
                                                <svg class="h-4 w-4 text-status-warn-text" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <path d="M18.36 5.64 5.64 18.36M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        @endif
                                        <button type="button" class="btn-icon" wire:confirm="Eliminar esta unidad solo si no tiene uso? Esta accion no se puede deshacer." wire:click="delete({{ $unit->id }})" aria-label="Eliminar unidad" title="Eliminar">
                                            <svg class="h-4 w-4 text-status-pending-text" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6h16Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-xs text-surface-muted">Solo consulta</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="table-cell py-8 text-center text-surface-muted">
                                No hay unidades que coincidan con los filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $units->links() }}
    </section>

    @if ($canManageUnits)
        <x-side-panel
            wire:model="showFormPanel"
            :title="$editingUnitId ? 'Editar unidad' : 'Nueva unidad'"
            subheading="Las unidades pertenecen a la empresa activa y a un centro."
            labelledby="organizational-unit-form-title"
        >
            <form wire:submit="save" class="flex flex-1 flex-col overflow-y-auto">
                <div class="flex-1 space-y-4 p-6">
                    <flux:select label="Centro" wire:model.live="form.center_id">
                        <flux:select.option value="">Selecciona un centro</flux:select.option>
                        @foreach ($centers as $center)
                            <flux:select.option value="{{ $center->id }}">{{ $center->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select label="Tipo" wire:model.live="form.type">
                        <flux:select.option value="department">Departamento</flux:select.option>
                        <flux:select.option value="area">Area</flux:select.option>
                        <flux:select.option value="team">Equipo</flux:select.option>
                    </flux:select>

                    @if ($form['type'] !== 'department')
                        <flux:select label="Unidad padre" wire:model="form.parent_id">
                            <flux:select.option value="">{{ $form['type'] === 'area' ? 'Centro directo o departamento' : 'Selecciona un area' }}</flux:select.option>
                            @foreach ($parentOptions as $parent)
                                <flux:select.option value="{{ $parent->id }}">{{ $parent->code }} - {{ $parent->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                    <flux:input label="Codigo" wire:model="form.code" required />
                    <flux:input label="Nombre" wire:model="form.name" required />

                    @if ($editingUnitId)
                        <flux:select label="Estado" wire:model="form.status">
                            <flux:select.option value="active">Activa</flux:select.option>
                            <flux:select.option value="inactive">Inactiva</flux:select.option>
                        </flux:select>
                    @endif

                    @error('form.parent_id')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end gap-3 border-t border-surface-line p-6">
                    <button type="button" class="btn-ghost" wire:click="closeFormPanel">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        Guardar unidad
                    </button>
                </div>
            </form>
        </x-side-panel>
    @endif
</section>
