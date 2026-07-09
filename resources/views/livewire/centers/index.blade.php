<?php

use App\Domains\Companies\Actions\CreateCenterAction;
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
            'address' => $center->address ? json_encode($center->address, JSON_PRETTY_PRINT) : '',
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
            'form.address' => ['nullable', 'json'],
        ])['form'];

        $data = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'timezone' => $validated['timezone'],
            'status' => $validated['status'],
            'address' => $this->decodeOptionalJson($validated['address'] ?? null),
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

    private function decodeOptionalJson(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'form.address' => 'La direccion debe ser un objeto JSON valido.',
            ]);
        }

        return $decoded;
    }

    private function emptyForm(?string $timezone = null): array
    {
        return [
            'code' => '',
            'name' => '',
            'timezone' => $timezone ?: 'America/Mexico_City',
            'status' => 'active',
            'address' => '',
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
            <flux:button type="button" variant="primary" wire:click="openCreatePanel">
                Nuevo centro
            </flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="mb-4">
            <flux:heading>Centros de {{ $currentCompany->name }}</flux:heading>
            <flux:subheading>Solo se muestran centros asociados a la empresa activa.</flux:subheading>
        </div>

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
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse ($centers as $center)
                        <tr>
                            <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $center->code }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $center->name }}</td>
                            <td class="px-4 py-3 text-zinc-700 dark:text-zinc-300">{{ $center->timezone }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium uppercase text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ $center->status }}
                                </span>
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
                        <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Estado</label>
                        <select wire:model="form.status" class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                        @error('form.status')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Direccion JSON opcional</label>
                        <textarea
                            wire:model="form.address"
                            rows="5"
                            class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100"
                        ></textarea>
                        @error('form.address')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
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
