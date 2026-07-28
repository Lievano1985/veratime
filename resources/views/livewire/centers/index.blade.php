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

    public function delete(int $centerId, CurrentCompany $currentCompany, DeleteCenterIfUnusedAction $action): void
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

<section class="w-full space-y-8 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Centros</flux:heading>
            <flux:subheading>Administra los centros de trabajo de la empresa activa.</flux:subheading>
        </div>

        @if ($canManageCenters)
            <flux:button type="button" icon="plus" variant="primary" wire:click="openCreatePanel">
                Nuevo centro
            </flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    @error('center')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            {{ $message }}
        </div>
    @enderror

    <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-5 dark:border-zinc-700 dark:bg-zinc-900/60">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <flux:heading>Centros de {{ $currentCompany->name }}</flux:heading>
                <flux:subheading>Solo se muestran centros asociados a la empresa activa.</flux:subheading>
            </div>

            <div class="w-full lg:max-w-sm">
                <flux:input wire:model.live.debounce.300ms="search" label="Buscar" placeholder="Codigo o nombre de centro" />
            </div>
        </div>
    </section>

    <div class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-700">
        <table class="w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
            <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="px-4 py-3">Codigo</th>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Zona horaria</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 [&>tr:nth-child(odd)]:bg-white [&>tr:nth-child(even)]:bg-zinc-50/60 dark:divide-zinc-700 dark:[&>tr:nth-child(odd)]:bg-zinc-900 dark:[&>tr:nth-child(even)]:bg-zinc-800/40">
                @forelse ($centers as $center)
                    <tr>
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $center->code }}</td>
                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $center->name }}</td>
                        <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $center->timezone }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge variant="{{ $center->status === 'active' ? 'success' : 'neutral' }}">
                                {{ $center->status }}
                            </x-ui.badge>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <flux:button type="button" size="sm" wire:click="loadEditForm({{ $center->id }})">
                                    Editar
                                </flux:button>

                                @if ($center->status === 'active')
                                    <flux:button type="button" size="sm" variant="danger" wire:click="inactivate({{ $center->id }})">
                                        Inactivar
                                    </flux:button>
                                @endif
                                <flux:button type="button" size="sm" variant="danger" wire:confirm="Eliminar este centro solo si no tiene uso? Esta accion no se puede deshacer." wire:click="delete({{ $center->id }})">
                                    Eliminar
                                </flux:button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                            Aun no hay centros registrados para esta empresa.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

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
                        <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Estado</label>
                        <select wire:model="form.status" class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        @error('form.status')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <section class="space-y-4 rounded-md border border-zinc-200 p-4 dark:border-zinc-700">
                        <div>
                            <flux:heading>Dirección</flux:heading>
                            <flux:subheading>Campos opcionales del centro de trabajo.</flux:subheading>
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

                <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:button type="button" variant="ghost" wire:click="closeFormPanel">
                        Cancelar
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Guardar centro
                    </flux:button>
                </div>
            </form>
        </x-side-panel>
    @endif
</section>
