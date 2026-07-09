<?php

use App\Domains\Companies\Actions\CreateCompanyAction;
use App\Domains\Companies\Actions\UpdateCompanyAction;
use App\Domains\Companies\Actions\UpdateCompanySettingsAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public array $createForm = [];
    public array $editForm = [];
    public array $settingsForm = [];
    public bool $showCreateDrawer = false;
    public ?int $editingCompanyId = null;

    public function mount(CurrentCompany $currentCompany): void
    {
        $this->createForm = $this->emptyCompanyForm();

        $company = $currentCompany->get();

        if ($company && Gate::allows('update', $company)) {
            $this->loadEditForm($company->id);
            $this->loadSettingsForm($company);
        }
    }

    public function create(CreateCompanyAction $action): void
    {
        Gate::authorize('create', Company::class);

        $validated = $this->validate([
            'createForm.name' => ['required', 'string', 'max:255'],
            'createForm.legal_name' => ['nullable', 'string', 'max:255'],
            'createForm.tax_id' => ['nullable', 'string', 'max:50', Rule::unique('companies', 'tax_id')],
            'createForm.timezone' => ['required', 'string', 'max:100'],
            'createForm.status' => ['required', Rule::in(['active', 'inactive', 'suspended', 'cancelled'])],
        ])['createForm'];

        $action->handle(auth()->user(), $validated);

        $this->createForm = $this->emptyCompanyForm();
        $this->showCreateDrawer = false;

        Session::flash('status', 'Empresa creada.');
    }

    public function openCreateDrawer(): void
    {
        Gate::authorize('create', Company::class);

        $this->showCreateDrawer = true;
    }

    public function closeCreateDrawer(): void
    {
        $this->showCreateDrawer = false;
        $this->resetValidation('createForm');
    }

    public function loadEditForm(int $companyId): void
    {
        $company = $this->authorizedCompany($companyId, 'update');

        $this->editingCompanyId = $company->id;
        $this->editForm = [
            'name' => $company->name,
            'legal_name' => $company->legal_name,
            'tax_id' => $company->tax_id,
            'timezone' => $company->timezone,
            'status' => $company->status,
        ];
    }

    public function update(UpdateCompanyAction $action): void
    {
        $company = $this->authorizedCompany($this->editingCompanyId, 'update');

        $validated = $this->validate([
            'editForm.name' => ['required', 'string', 'max:255'],
            'editForm.legal_name' => ['nullable', 'string', 'max:255'],
            'editForm.tax_id' => ['nullable', 'string', 'max:50', Rule::unique('companies', 'tax_id')->ignore($company->id)],
            'editForm.timezone' => ['required', 'string', 'max:100'],
            'editForm.status' => ['required', Rule::in(['active', 'inactive', 'suspended', 'cancelled'])],
        ])['editForm'];

        $company = $action->handle($company, $validated);

        if ($company->status !== 'active' && session('current_company_id') === $company->id) {
            session()->forget('current_company_id');
            $this->editingCompanyId = null;
            $this->editForm = [];

            Session::flash('status', 'Empresa actualizada.');

            return;
        }

        $this->loadEditForm($company->id);

        Session::flash('status', 'Empresa actualizada.');
    }

    public function updateSettings(UpdateCompanySettingsAction $action, CurrentCompany $currentCompany): void
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);
        Gate::authorize('update', $company);

        $validated = $this->validate([
            'settingsForm.payroll_period_type' => ['required', Rule::in(['weekly', 'biweekly', 'monthly', 'custom'])],
            'settingsForm.default_timezone' => ['required', 'string', 'max:100'],
            'settingsForm.default_closure_day' => ['nullable', 'integer', 'between:1,31'],
            'settingsForm.allow_worker_corrections' => ['boolean'],
            'settingsForm.require_pin_for_kiosk' => ['boolean'],
            'settingsForm.require_pin_for_confirmation' => ['boolean'],
        ])['settingsForm'];

        $action->handle($company, $validated);
        $this->loadSettingsForm($company->refresh());

        Session::flash('status', 'Configuracion actualizada.');
    }

    public function with(CurrentCompany $currentCompany): array
    {
        return [
            'companies' => auth()->user()
                ->activeCompanies()
                ->orderBy('name')
                ->get(),
            'currentCompany' => $currentCompany->get(),
            'canCreateCompany' => Gate::allows('create', Company::class),
            'canManageCurrentCompany' => $currentCompany->get()
                ? Gate::allows('update', $currentCompany->get())
                : false,
        ];
    }

    private function authorizedCompany(?int $companyId, string $ability): Company
    {
        abort_unless($companyId, 404);

        $company = auth()->user()
            ->activeCompanies()
            ->whereKey($companyId)
            ->firstOrFail();

        Gate::authorize($ability, $company);

        return $company;
    }

    private function loadSettingsForm(Company $company): void
    {
        $settings = array_merge(Company::defaultSettings(), $company->setting?->toArray() ?? []);

        $this->settingsForm = [
            'payroll_period_type' => $settings['payroll_period_type'],
            'default_timezone' => $settings['default_timezone'] ?? $company->timezone,
            'default_closure_day' => $settings['default_closure_day'],
            'allow_worker_corrections' => (bool) $settings['allow_worker_corrections'],
            'require_pin_for_kiosk' => (bool) $settings['require_pin_for_kiosk'],
            'require_pin_for_confirmation' => (bool) $settings['require_pin_for_confirmation'],
        ];
    }

    private function emptyCompanyForm(): array
    {
        return [
            'name' => '',
            'legal_name' => '',
            'tax_id' => '',
            'timezone' => 'America/Mexico_City',
            'status' => 'active',
        ];
    }
}; ?>

<section class="w-full space-y-8 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Empresas</flux:heading>
            <flux:subheading>Administra la empresa activa y su configuracion inicial.</flux:subheading>
        </div>

        @if ($canCreateCompany)
            <flux:button type="button" variant="primary" wire:click="openCreateDrawer">
                Nueva empresa
            </flux:button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_420px]">
        <div class="space-y-6">
            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-4">
                    <flux:heading>Empresas autorizadas</flux:heading>
                    <flux:subheading>Solo se listan empresas activas donde tu relacion tambien esta activa.</flux:subheading>
                </div>

                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($companies as $company)
                        <div class="flex w-full items-center justify-between gap-4 py-3 text-left">
                            <span>
                                <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ $company->name }}</span>
                                <span class="block text-sm text-zinc-500 dark:text-zinc-400">{{ $company->tax_id ?: 'Sin RFC' }}</span>
                            </span>

                            <span class="flex items-center gap-3">
                                <span class="text-xs font-medium uppercase text-zinc-500 dark:text-zinc-400">{{ $company->status }}</span>

                                @if ($canManageCurrentCompany)
                                    <flux:button type="button" size="sm" wire:click="loadEditForm({{ $company->id }})">Editar</flux:button>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            </section>

            @if ($editingCompanyId && $canManageCurrentCompany)
                <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4">
                        <flux:heading>Datos basicos</flux:heading>
                        <flux:subheading>Editar informacion general y estado operativo de la empresa.</flux:subheading>
                    </div>

                    <form wire:submit="update" class="space-y-4">
                        <flux:input wire:model="editForm.name" label="Nombre comercial" required />
                        <flux:input wire:model="editForm.legal_name" label="Razon social" />
                        <flux:input wire:model="editForm.tax_id" label="RFC" />
                        <flux:input wire:model="editForm.timezone" label="Zona horaria" required />

                        <div>
                            <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Estado</label>
                            <select wire:model="editForm.status" class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                <option value="active">Activa</option>
                                <option value="inactive">Inactiva</option>
                                <option value="suspended">Suspendida</option>
                                <option value="cancelled">Cancelada</option>
                            </select>
                            @error('editForm.status')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <flux:button type="submit" variant="primary">Guardar empresa</flux:button>
                    </form>
                </section>
            @endif
        </div>

        <div class="space-y-6">
            @if ($currentCompany && $canManageCurrentCompany)
                <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="mb-4">
                        <flux:heading>Configuracion</flux:heading>
                        <flux:subheading>Parametros iniciales de cierre, kiosco, correcciones y conformidad.</flux:subheading>
                    </div>

                    <form wire:submit="updateSettings" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Periodo de cierre</label>
                            <select wire:model="settingsForm.payroll_period_type" class="w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100">
                                <option value="weekly">Semanal</option>
                                <option value="biweekly">Quincenal</option>
                                <option value="monthly">Mensual</option>
                                <option value="custom">Personalizado</option>
                            </select>
                            @error('settingsForm.payroll_period_type')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <flux:input wire:model="settingsForm.default_timezone" label="Zona horaria" required />
                        <flux:input wire:model="settingsForm.default_closure_day" label="Dia de cierre" type="number" min="1" max="31" />

                        <div class="space-y-3">
                            <flux:checkbox wire:model="settingsForm.allow_worker_corrections" label="Permitir solicitudes de correccion" />
                            <flux:checkbox wire:model="settingsForm.require_pin_for_kiosk" label="Requerir NIP en kiosco" />
                            <flux:checkbox wire:model="settingsForm.require_pin_for_confirmation" label="Requerir NIP para conformidad" />
                        </div>

                        <flux:button type="submit" variant="primary">Guardar configuracion</flux:button>
                    </form>
                </section>
            @endif
        </div>
    </div>

    @if ($canCreateCompany)
        <x-side-panel
            wire:model="showCreateDrawer"
            title="Nueva empresa"
            subheading="La empresa queda asociada a tu usuario como propietario."
            labelledby="create-company-title"
        >
            <form wire:submit="create" class="flex flex-1 flex-col overflow-y-auto">
                <div class="flex-1 space-y-4 p-6">
                    <flux:input wire:model="createForm.name" label="Nombre comercial" required />
                    <flux:input wire:model="createForm.legal_name" label="Razon social" />
                    <flux:input wire:model="createForm.tax_id" label="RFC" />
                    <flux:input wire:model="createForm.timezone" label="Zona horaria" required />
                </div>

                <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:button type="button" variant="ghost" wire:click="closeCreateDrawer">
                        Cancelar
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        Crear empresa
                    </flux:button>
                </div>
            </form>
        </x-side-panel>
    @endif
</section>
