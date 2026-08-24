<?php

use App\Domains\Companies\Actions\CreateCenterAction;
use App\Domains\Companies\Actions\DeleteCenterIfUnusedAction;
use App\Domains\Companies\Actions\InactivateCenterAction;
use App\Domains\Companies\Actions\UpdateCenterAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Center;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public array $form = [];
    public bool $showFormPanel = false;
    public ?int $editingCenterId = null;
    public string $search = '';

    public function mount(): void
    {
        $this->form = $this->emptyForm();
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [Center::class, $company]);

        $this->editingCenterId = null;
        $this->form = $this->emptyForm($company->timezone);
        $this->showFormPanel = true;
    }

    public function loadEditForm(int $centerId, CurrentCompany $currentCompany): void
    {
        $center = $this->authorizedCenter($centerId, $currentCompany);

        $this->editingCenterId = $center->id;
        $this->form = [
            'code' => $center->code,
            'name' => $center->name,
            'timezone' => $center->timezone,
            'status' => $center->status,
            'address' => $this->addressFormFromValue($center->address),
        ];
        $this->showFormPanel = true;
    }

    public function save(
        CurrentCompany $currentCompany,
        CreateCenterAction $createAction,
        UpdateCenterAction $updateAction,
    ): void {
        $company = $this->currentCompanyOrFail($currentCompany);

        $center = $this->editingCenterId
            ? $this->authorizedCenter($this->editingCenterId, $currentCompany)
            : null;

        $center
            ? Gate::authorize('update', $center)
            : Gate::authorize('create', [Center::class, $company]);

        $validated = $this->validate([
            'form.code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('centers', 'code')
                    ->where('company_id', $company->id)
                    ->ignore($center?->id),
            ],
            'form.name' => ['required', 'string', 'max:255'],
            'form.timezone' => ['required', 'string', 'max:100'],
            'form.status' => ['required', Rule::in(['active', 'inactive'])],
            'form.address.street' => ['nullable', 'string', 'max:255'],
            'form.address.exterior_number' => ['nullable', 'string', 'max:50'],
            'form.address.interior_number' => ['nullable', 'string', 'max:50'],
            'form.address.neighborhood' => ['nullable', 'string', 'max:255'],
            'form.address.postal_code' => ['nullable', 'string', 'max:20'],
            'form.address.municipality' => ['nullable', 'string', 'max:255'],
            'form.address.city' => ['nullable', 'string', 'max:255'],
            'form.address.state' => ['nullable', 'string', 'max:255'],
            'form.address.country' => ['nullable', 'string', 'max:255'],
            'form.address.country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'form.address.jurisdiction_code' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z]{2}-[A-Za-z0-9]{2,8}$/'],
        ])['form'];

        $data = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'status' => $validated['status'],
            'address' => $this->cleanAddress($validated['address'] ?? []),
        ];

        $center
            ? $updateAction->handle($center, $data)
            : $createAction->handle($company, $data);

        $this->showFormPanel = false;
        $this->editingCenterId = null;
        $this->form = $this->emptyForm($company->timezone);

        Session::flash('status', $center ? 'Centro actualizado.' : 'Centro creado.');
    }

    public function inactivate(
        int $centerId,
        CurrentCompany $currentCompany,
        InactivateCenterAction $action,
    ): void {
        $center = $this->authorizedCenter($centerId, $currentCompany);

        Gate::authorize('inactivate', $center);

        $action->handle($center);

        Session::flash('status', 'Centro inactivado.');
    }

    public function deleteCenter(int $centerId, CurrentCompany $currentCompany, DeleteCenterIfUnusedAction $action): void
    {
        $center = $this->authorizedCenter($centerId, $currentCompany);

        Gate::authorize('delete', $center);

        try {
            $action->handle($center);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['center' => $exception->getMessage()]);
        }

        Session::flash('status', 'Centro eliminado.');
    }

    public function closeFormPanel(): void
    {
        $this->showFormPanel = false;
        $this->editingCenterId = null;
        $this->resetValidation('form');
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        Gate::authorize('viewAny', [Center::class, $company]);

        return [
            'centers' => $company->centers()
                ->when($this->search !== '', function ($query): void {
                    $search = '%'.$this->search.'%';

                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('code', 'like', $search)
                            ->orWhere('name', 'like', $search);
                    });
                })
                ->orderBy('name')
                ->get(),
            'currentCompany' => $company,
            'canManageCenters' => Gate::allows('create', [Center::class, $company]),
        ];
    }

    private function authorizedCenter(int $centerId, CurrentCompany $currentCompany): Center
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        $center = $company->centers()
            ->whereKey($centerId)
            ->firstOrFail();

        Gate::authorize('update', $center);

        return $center;
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany)
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function addressFormFromValue(?array $address): array
    {
        return array_merge($this->emptyAddress(), array_intersect_key($address ?? [], $this->emptyAddress()));
    }

    private function cleanAddress(array $address): ?array
    {
        $clean = collect($this->emptyAddress())
            ->mapWithKeys(fn ($default, $key) => [$key => trim((string) ($address[$key] ?? ''))])
            ->filter(fn (string $value) => $value !== '')
            ->all();

        return $clean === [] ? null : $clean;
    }

    private function emptyAddress(): array
    {
        return [
            'street' => '',
            'exterior_number' => '',
            'interior_number' => '',
            'neighborhood' => '',
            'postal_code' => '',
            'municipality' => '',
            'city' => '',
            'state' => '',
            'country' => 'Mexico',
            'country_code' => 'MX',
            'jurisdiction_code' => '',
        ];
    }

    private function emptyForm(?string $timezone = null): array
    {
        return [
            'code' => '',
            'name' => '',
            'timezone' => $timezone ?: 'America/Mexico_City',
            'status' => 'active',
            'address' => $this->emptyAddress(),
        ];
    }
}; ?>

<section class="w-full space-y-8 bg-surface-bg p-6 text-surface-text">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-brand-navy">Centros</h1>
            <p class="mt-1.5 text-[13.5px] text-surface-muted">Administra los centros de trabajo de la empresa activa.</p>
        </div>

        @if ($canManageCenters)
            <button type="button" class="btn-primary" wire:click="openCreatePanel">
                <span class="text-base leading-none">+</span>
                Nuevo centro
            </button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-status-rest-line bg-status-rest-bg px-4 py-3 text-sm font-medium text-status-rest-text">
            {{ session('status') }}
        </div>
    @endif

    @error('center')
        <div class="rounded-xl border border-status-pending-line bg-status-pending-bg px-4 py-3 text-sm font-medium text-status-pending-text">
            {{ $message }}
        </div>
    @enderror

    <section class="rounded-2xl border border-surface-line bg-surface-card p-6 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="font-display text-lg font-bold text-brand-navy">Centros de {{ $currentCompany->name }}</h2>
                <p class="mt-1 text-[13px] text-surface-muted">Solo se muestran centros asociados a la empresa activa.</p>
            </div>

            <div class="w-full lg:max-w-sm">
                <flux:input wire:model.live.debounce.300ms="search" label="Buscar" placeholder="Codigo o nombre de centro" />
            </div>
        </div>

        <div class="table-wrap mt-5">
        <table class="w-full border-collapse">
            <colgroup>
                <col class="w-[14%]">
                <col class="w-[34%]">
                <col class="w-[24%]">
                <col class="w-[14%]">
                <col class="w-[14%]">
            </colgroup>
            <thead>
                <tr>
                    <th class="table-head-cell">Codigo</th>
                    <th class="table-head-cell">Nombre</th>
                    <th class="table-head-cell">Zona horaria</th>
                    <th class="table-head-cell">Estado</th>
                    <th class="table-head-cell text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($centers as $center)
                    <tr class="table-row">
                        <td class="table-cell font-mono text-brand-navy">{{ $center->code }}</td>
                        <td class="table-cell font-semibold text-brand-navy">{{ $center->name }}</td>
                        <td class="table-cell text-surface-muted">{{ $center->timezone }}</td>
                        <td class="table-cell">
                            <span class="{{ $center->status === 'active' ? 'badge-success' : 'badge-muted' }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                {{ $center->status }}
                            </span>
                        </td>
                        <td class="table-cell">
                            <div class="flex justify-end gap-1.5">
                                <button type="button" class="btn-icon" wire:click="loadEditForm({{ $center->id }})" aria-label="Editar centro" title="Editar">
                                    <svg class="h-4 w-4 text-surface-muted" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>

                                @if ($center->status === 'active')
                                    <button type="button" class="btn-icon" wire:click="inactivate({{ $center->id }})" aria-label="Inactivar centro" title="Inactivar">
                                        <svg class="h-4 w-4 text-status-warn-text" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M18.36 5.64 5.64 18.36M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                @endif
                                <button type="button" class="btn-icon" wire:confirm="Eliminar este centro solo si no tiene uso? Esta accion no se puede deshacer." wire:click="deleteCenter({{ $center->id }})" aria-label="Eliminar centro" title="Eliminar">
                                    <svg class="h-4 w-4 text-status-pending-text" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6h16Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="table-cell py-8 text-center text-surface-muted">
                            Aun no hay centros registrados para esta empresa.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </section>

    @if ($canManageCenters)
        <x-side-panel
            wire:model="showFormPanel"
            :title="$editingCenterId ? 'Editar centro' : 'Nuevo centro'"
            subheading="Los datos aplican solo a la empresa activa."
            labelledby="center-form-title"
        >
            <form wire:submit="save" class="flex flex-1 flex-col overflow-y-auto">
                <div class="flex-1 space-y-4 p-6">
                    <flux:input wire:model="form.code" label="Codigo" required />
                    <flux:input wire:model="form.name" label="Nombre" required />
                    <flux:input wire:model="form.timezone" label="Zona horaria" required />

                    <div>
                        <label class="form-label">Estado</label>
                        <select wire:model="form.status" class="form-select">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        @error('form.status')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <section class="space-y-4 rounded-2xl border border-surface-line bg-surface-card p-4">
                        <div>
                            <h3 class="font-display text-sm font-bold text-brand-navy">Dirección</h3>
                            <p class="text-xs text-surface-muted">Campos opcionales del centro de trabajo.</p>
                        </div>

                        <flux:input wire:model="form.address.street" label="Calle" />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="form.address.exterior_number" label="Número exterior" />
                            <flux:input wire:model="form.address.interior_number" label="Número interior" />
                        </div>

                        <flux:input wire:model="form.address.neighborhood" label="Colonia" />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="form.address.postal_code" label="Código postal" />
                            <flux:input wire:model="form.address.municipality" label="Municipio" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="form.address.city" label="Ciudad" />
                            <flux:input wire:model="form.address.state" label="Estado" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:input wire:model="form.address.country" label="Pais" disabled />
                            <flux:input wire:model="form.address.jurisdiction_code" label="Entidad federativa" placeholder="MX-TAB" />
                        </div>
                    </section>
                </div>

                <div class="flex justify-end gap-3 border-t border-surface-line p-6">
                    <button type="button" class="btn-ghost" wire:click="closeFormPanel">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        Guardar centro
                    </button>
                </div>
            </form>
        </x-side-panel>
    @endif
</section>
