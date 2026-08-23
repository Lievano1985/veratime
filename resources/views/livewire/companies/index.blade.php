<?php

use App\Domains\Companies\Actions\CreateCompanyAction;
use App\Domains\Companies\Actions\CreateTenantWithAdminAction;
use App\Domains\Companies\Actions\DeleteCompanyAction;
use App\Domains\Companies\Actions\UpdateCompanyAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use App\Models\Role;
use App\Support\RoleKey;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public array $createForm = [];
    public array $editForm = [];
    public bool $showCreateDrawer = false;
    public bool $showDeleteDrawer = false;
    public ?int $editingCompanyId = null;
    public ?int $deletingCompanyId = null;
    public ?string $temporaryPassword = null;
    public string $companySearch = '';
    public string $adminEmailSearch = '';
    public string $deleteConfirmation = '';

    public function mount(CurrentCompany $currentCompany): void
    {
        $this->createForm = $this->emptyCompanyForm();

        $company = $currentCompany->get();

        if ($company && Gate::allows('update', $company)) {
            $this->loadEditForm($company->id);
        }
    }

    public function create(CreateCompanyAction $createCompany, CreateTenantWithAdminAction $createTenant): void
    {
        Gate::authorize('create', Company::class);

        $rules = [
            'createForm.name' => ['required', 'string', 'max:255'],
            'createForm.legal_name' => ['nullable', 'string', 'max:255'],
            'createForm.tax_id' => ['nullable', 'string', 'max:50', Rule::unique('companies', 'tax_id')],
            'createForm.timezone' => ['required', 'string', 'max:100'],
            'createForm.status' => ['required', Rule::in(auth()->user()->isSuperAdmin() ? ['active', 'inactive', 'suspended', 'cancelled'] : ['active', 'inactive'])],
        ];

        if (auth()->user()->isSuperAdmin()) {
            $rules += [
                'createForm.account_type' => ['required', Rule::in(['single_company', 'multi_company'])],
                'createForm.admin_name' => ['required', 'string', 'max:255'],
                'createForm.admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
                'createForm.admin_password' => ['required', 'string', 'min:8', 'max:100', 'regex:/^(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).+$/'],
                'createForm.admin_status' => ['required', Rule::in(['active', 'inactive'])],
            ];
        }

        $validated = $this->validate($rules)['createForm'];

        if (auth()->user()->isSuperAdmin()) {
            $company = $createTenant->handle(auth()->user(), [
                'company' => [
                    'name' => $validated['name'],
                    'legal_name' => $validated['legal_name'] ?? null,
                    'tax_id' => $validated['tax_id'] ?? null,
                    'timezone' => $validated['timezone'],
                    'status' => $validated['status'],
                    'account_type' => $validated['account_type'],
                ],
                'admin' => [
                    'name' => $validated['admin_name'],
                    'email' => $validated['admin_email'],
                    'password' => $validated['admin_password'],
                    'status' => $validated['admin_status'],
                ],
            ]);

            $this->temporaryPassword = $validated['admin_password'];

            if ($company->status === 'active') {
                session(['current_company_id' => $company->id]);
                app(CurrentCompany::class)->set($company);
            }

            $this->dispatch('companies-updated');
            Session::flash('status', 'Empresa y administrador principal creados. Copia la contraseña temporal antes de continuar.');
        } else {
            $createCompany->handle(auth()->user(), $validated);
            $this->dispatch('companies-updated');
            Session::flash('status', 'Empresa creada.');
        }

        $this->createForm = $this->emptyCompanyForm();
        $this->showCreateDrawer = false;
    }

    public function openCreateDrawer(): void
    {
        Gate::authorize('create', Company::class);

        $this->createForm = $this->emptyCompanyForm();
        $this->temporaryPassword = null;

        if (auth()->user()->isSuperAdmin()) {
            $this->createForm['admin_password'] = $this->generateTemporaryPassword();
        }

        $this->showCreateDrawer = true;
    }

    public function closeCreateDrawer(): void
    {
        $this->showCreateDrawer = false;
        $this->resetValidation('createForm');
    }

    public function clearTemporaryPassword(): void
    {
        $this->temporaryPassword = null;
    }

    public function updatedCompanySearch(): void
    {
        $this->resetPage();
    }

    public function updatedAdminEmailSearch(): void
    {
        $this->resetPage();
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
            'account_type' => $company->customerAccount?->account_type ?? 'single_company',
        ];
    }

    public function update(UpdateCompanyAction $action): void
    {
        $company = $this->authorizedCompany($this->editingCompanyId, 'update');

        $rules = [
            'editForm.name' => ['required', 'string', 'max:255'],
            'editForm.legal_name' => ['nullable', 'string', 'max:255'],
            'editForm.tax_id' => ['nullable', 'string', 'max:50', Rule::unique('companies', 'tax_id')->ignore($company->id)],
            'editForm.timezone' => ['required', 'string', 'max:100'],
            'editForm.status' => ['required', Rule::in(auth()->user()->isSuperAdmin() ? ['active', 'inactive', 'suspended', 'cancelled'] : ['active', 'inactive'])],
        ];

        if (auth()->user()->isSuperAdmin()) {
            $rules['editForm.account_type'] = ['required', Rule::in(['single_company', 'multi_company'])];
        }

        $validated = $this->validate($rules)['editForm'];

        if (! auth()->user()->isSuperAdmin()) {
            unset($validated['account_type']);
        }

        $company = $action->handle($company, $validated, auth()->user());

        if ($company->status !== 'active' && session('current_company_id') === $company->id) {
            session()->forget('current_company_id');
            app(CurrentCompany::class)->clear();
        }

        $this->loadEditForm($company->id);

        Session::flash('status', 'Empresa actualizada.');
    }

    public function openDeleteDrawer(int $companyId): void
    {
        $company = $this->authorizedCompany($companyId, 'delete');

        $this->deletingCompanyId = $company->id;
        $this->deleteConfirmation = '';
        $this->showDeleteDrawer = true;
    }

    public function closeDeleteDrawer(): void
    {
        $this->showDeleteDrawer = false;
        $this->deletingCompanyId = null;
        $this->deleteConfirmation = '';
        $this->resetValidation('deleteConfirmation');
    }

    public function delete(DeleteCompanyAction $action): void
    {
        $company = $this->authorizedCompany($this->deletingCompanyId, 'delete');

        $this->validate([
            'deleteConfirmation' => ['required', 'string', Rule::in([$company->name])],
        ], [
            'deleteConfirmation.in' => 'Escribe exactamente el nombre de la empresa para confirmar.',
        ]);

        $deletedCompanyId = $company->id;

        $action->handle(auth()->user(), $company);

        if (session('current_company_id') === $deletedCompanyId) {
            session()->forget('current_company_id');
            app(CurrentCompany::class)->clear();
        }

        if ($this->editingCompanyId === $deletedCompanyId) {
            $this->editingCompanyId = null;
            $this->editForm = [];
        }

        $this->closeDeleteDrawer();
        $this->resetPage();
        $this->dispatch('companies-updated');

        Session::flash('status', 'Empresa eliminada.');
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $currentCompany->get();
        $canManageCurrentCompany = $company ? Gate::allows('update', $company) : false;

        return [
            'companies' => $this->companyList(),
            'singleCompanySummary' => $this->singleCompanySummary($company),
            'currentCompany' => $company,
            'canCreateCompany' => Gate::allows('create', Company::class),
            'canManageCurrentCompany' => $canManageCurrentCompany,
            'canManageEditingCompany' => $this->canManageEditingCompany(),
            'deletingCompany' => $this->deletingCompany(),
            'isSuperAdmin' => auth()->user()->isSuperAdmin(),
            'showCompanyDirectory' => $this->shouldShowCompanyDirectory(),
        ];
    }

    private function shouldShowCompanyDirectory(): bool
    {
        if (auth()->user()->isSuperAdmin()) {
            return true;
        }

        return auth()->user()
            ->companiesWithActiveMembership()
            ->count() > 1;
    }

    private function singleCompanySummary(?Company $currentCompany): ?Company
    {
        if ($this->shouldShowCompanyDirectory()) {
            return null;
        }

        return $currentCompany
            ?? auth()->user()
                ->companiesWithActiveMembership()
                ->orderBy('name')
                ->first();
    }

    private function companyList()
    {
        $adminRoleId = Role::query()
            ->where('key', RoleKey::ADMIN_EMPRESA)
            ->value('id');

        if (auth()->user()->isSuperAdmin()) {
            $query = Company::query();
        } else {
            $query = auth()->user()
                ->companiesWithActiveMembership();
        }

        return $query
            ->when($this->companySearch !== '', function ($query): void {
                $search = trim($this->companySearch);

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('companies.name', 'like', "%{$search}%")
                        ->orWhere('companies.legal_name', 'like', "%{$search}%")
                        ->orWhere('companies.tax_id', 'like', "%{$search}%");
                });
            })
            ->when($this->adminEmailSearch !== '', function ($query) use ($adminRoleId): void {
                $search = trim($this->adminEmailSearch);

                $query->whereHas('users', function ($query) use ($search, $adminRoleId): void {
                    $query
                        ->where('users.email', 'like', "%{$search}%")
                        ->where('company_user.status', 'active')
                        ->when($adminRoleId, fn ($query) => $query->where('company_user.role_id', $adminRoleId));
                });
            })
            ->with([
                'customerAccount',
                'users' => function ($query) use ($adminRoleId): void {
                $query
                    ->where('company_user.status', 'active')
                    ->when($adminRoleId, fn ($query) => $query->where('company_user.role_id', $adminRoleId))
                    ->orderBy('users.name');
                },
            ])
            ->orderBy('name')
            ->paginate(5);
    }

    private function authorizedCompany(?int $companyId, string $ability): Company
    {
        abort_unless($companyId, 404);

        $company = auth()->user()->isSuperAdmin()
            ? Company::query()->whereKey($companyId)->firstOrFail()
            : auth()->user()
                ->companiesWithActiveMembership()
                ->whereKey($companyId)
                ->firstOrFail();

        Gate::authorize($ability, $company);

        return $company;
    }

    private function canManageEditingCompany(): bool
    {
        if (! $this->editingCompanyId) {
            return false;
        }

        $company = Company::query()->find($this->editingCompanyId);

        return $company ? Gate::allows('update', $company) : false;
    }

    private function deletingCompany(): ?Company
    {
        if (! $this->deletingCompanyId) {
            return null;
        }

        return Company::query()->find($this->deletingCompanyId);
    }

    private function emptyCompanyForm(): array
    {
        return [
            'name' => '',
            'legal_name' => '',
            'tax_id' => '',
            'timezone' => 'America/Mexico_City',
            'status' => 'active',
            'account_type' => 'single_company',
            'admin_name' => '',
            'admin_email' => '',
            'admin_password' => '',
            'admin_status' => 'active',
        ];
    }

    private function generateTemporaryPassword(): string
    {
        return 'Vt'.random_int(10, 99).'!'.Str::random(12);
    }
}; ?>

<section class="w-full space-y-8 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Empresas</flux:heading>
            <flux:subheading>Administra empresas y datos generales. La configuración operativa vive en Configuración de empresa.</flux:subheading>
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

    @if ($temporaryPassword)
        <div class="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="font-semibold">Contraseña temporal del administrador principal</div>
                <div class="font-mono text-base">{{ $temporaryPassword }}</div>
            </div>
            <flux:button type="button" size="sm" variant="ghost" wire:click="clearTemporaryPassword">Ocultar</flux:button>
        </div>
    @endif

    <div class="space-y-6">
        @if (! $showCompanyDirectory && $singleCompanySummary)
            <section class="rounded-lg border border-primary-border bg-primary-soft p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <flux:heading>Empresa actual</flux:heading>
                        <p class="mt-2 text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ $singleCompanySummary->name }}</p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $singleCompanySummary->tax_id ?: 'Sin RFC' }}</p>
                    </div>

                    <x-ui.badge variant="{{ $singleCompanySummary->status === 'active' ? 'success' : 'neutral' }}">
                        {{ $singleCompanySummary->status }}
                    </x-ui.badge>
                </div>
            </section>
        @endif

        @if ($showCompanyDirectory)
            <section class="rounded-lg border border-primary-border bg-primary-soft p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-4">
                    <flux:heading>Empresas autorizadas</flux:heading>
                    <flux:subheading>Se listan las empresas disponibles para administración. Las inactivas no aparecen en el selector operativo.</flux:subheading>
                </div>

                <div class="mb-4 grid gap-3 md:grid-cols-2">
                    <flux:input
                        wire:model.live.debounce.300ms="companySearch"
                        label="Buscar empresa"
                        placeholder="Nombre, razón social o RFC"
                    />
                    <flux:input
                        wire:model.live.debounce.300ms="adminEmailSearch"
                        label="Correo administrador"
                        placeholder="admin@empresa.com"
                    />
                </div>

                <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="max-h-[360px] overflow-y-auto">
                        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($companies as $company)
                                @php($principalAdmin = $company->users->first())

                                <div class="flex w-full items-center justify-between gap-4 px-4 py-3 text-left">
                                    <span>
                                        <span class="block font-medium text-zinc-900 dark:text-zinc-100">{{ $company->name }}</span>
                                        <span class="block text-sm text-zinc-500 dark:text-zinc-400">{{ $company->tax_id ?: 'Sin RFC' }}</span>
                                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $principalAdmin?->email ? 'Admin: '.$principalAdmin->email : 'Sin administrador principal activo' }}
                                        </span>
                                    </span>

                                    <span class="flex shrink-0 items-center gap-3">
                                        <x-ui.badge variant="{{ $company->status === 'active' ? 'success' : 'neutral' }}">
                                            {{ $company->status }}
                                        </x-ui.badge>

                                        @can('update', $company)
                                            <flux:button type="button" size="sm" wire:click="loadEditForm({{ $company->id }})">Editar</flux:button>
                                        @endcan

                                        @can('delete', $company)
                                            <flux:button type="button" size="sm" variant="danger" wire:click="openDeleteDrawer({{ $company->id }})">Eliminar</flux:button>
                                        @endcan
                                    </span>
                                </div>
                            @empty
                                <div class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                    No hay empresas que coincidan con los filtros.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    {{ $companies->links() }}
                </div>
            </section>
        @endif

            @if ($editingCompanyId && $canManageEditingCompany)
                <section class="rounded-lg border border-zinc-200 bg-zinc-50/60 p-5 dark:border-zinc-700 dark:bg-zinc-800/40">
                    <div class="mb-4">
                        <flux:heading>Datos basicos</flux:heading>
                        <flux:subheading>Editar informacion general y estado operativo de la empresa.</flux:subheading>
                    </div>

                    <form wire:submit="update" class="space-y-4">
                        <flux:input wire:model="editForm.name" label="Nombre comercial" required />
                        <flux:input wire:model="editForm.legal_name" label="Razón social" />
                        <flux:input wire:model="editForm.tax_id" label="RFC" />
                        <flux:input wire:model="editForm.timezone" label="Zona horaria" required />

                        <div>
                            <label class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Estado</label>
                            <x-ui.select wire:model="editForm.status">
                                <option value="active">Activa</option>
                                <option value="inactive">Inactiva</option>
                                @if ($isSuperAdmin)
                                    <option value="suspended">Suspendida</option>
                                    <option value="cancelled">Cancelada</option>
                                @endif
                            </x-ui.select>
                            @error('editForm.status')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($isSuperAdmin)
                            <flux:select wire:model="editForm.account_type" label="Tipo de cuenta cliente">
                                <flux:select.option value="single_company">Monoempresa</flux:select.option>
                                <flux:select.option value="multi_company">Multiempresa</flux:select.option>
                            </flux:select>
                        @endif

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <flux:button type="submit" variant="primary">Guardar empresa</flux:button>

                            @if ($isSuperAdmin)
                                <flux:button type="button" variant="danger" wire:click="openDeleteDrawer({{ $editingCompanyId }})">
                                    Eliminar empresa
                                </flux:button>
                            @endif
                        </div>
                    </form>
                </section>
            @endif
    </div>

    @if ($canCreateCompany)
        <x-side-panel
            wire:model="showCreateDrawer"
            title="Nueva empresa"
            subheading="{{ $isSuperAdmin ? 'Crea la empresa y su administrador principal en un solo paso.' : 'Crea una empresa asociada a tu usuario.' }}"
            labelledby="create-company-title"
            max-width="max-w-2xl"
        >
            <form wire:submit="create" class="flex flex-1 flex-col overflow-y-auto">
                <div class="flex-1 space-y-6 p-6">
                    <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-4">
                        <div>
                            <h3 class="text-sm font-semibold text-zinc-900">Datos de empresa</h3>
                            <p class="text-xs text-zinc-500">Información base del tenant operativo.</p>
                        </div>
                        <flux:input wire:model="createForm.name" label="Nombre comercial" required />
                        <flux:input wire:model="createForm.legal_name" label="Razón social" />
                        <flux:input wire:model="createForm.tax_id" label="RFC" />
                        <flux:input wire:model="createForm.timezone" label="Zona horaria" required />
                        <flux:select wire:model="createForm.status" label="Estado inicial">
                            <flux:select.option value="active">Activa</flux:select.option>
                            <flux:select.option value="inactive">Inactiva</flux:select.option>
                            @if ($isSuperAdmin)
                                <flux:select.option value="suspended">Suspendida</flux:select.option>
                                <flux:select.option value="cancelled">Cancelada</flux:select.option>
                            @endif
                        </flux:select>

                        @if ($isSuperAdmin)
                            <flux:select wire:model="createForm.account_type" label="Tipo de cuenta cliente">
                                <flux:select.option value="single_company">Monoempresa</flux:select.option>
                                <flux:select.option value="multi_company">Multiempresa</flux:select.option>
                            </flux:select>
                            <p class="text-xs text-zinc-500">Define si esta cuenta cliente operara una sola empresa o podra agrupar varias empresas bajo la misma cuenta.</p>
                        @endif
                    </section>

                    @if ($isSuperAdmin)
                        <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-4">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-900">Administrador principal</h3>
                                <p class="text-xs text-zinc-500">Este usuario quedara como administrador general de la empresa. El super admin no se agrega como miembro operativo.</p>
                            </div>
                            <flux:input wire:model="createForm.admin_name" label="Nombre" required />
                            <flux:input wire:model="createForm.admin_email" type="email" label="Correo" required />
                            <flux:input wire:model="createForm.admin_password" label="Contraseña temporal" required />
                            <p class="text-xs text-zinc-500">Debe tener mínimo 8 caracteres, una mayúscula, un número y un símbolo.</p>
                            <flux:select wire:model="createForm.admin_status" label="Estado inicial del usuario">
                                <flux:select.option value="active">Activo</flux:select.option>
                                <flux:select.option value="inactive">Inactivo</flux:select.option>
                            </flux:select>
                        </section>
                    @endif
                </div>

                <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:button type="button" variant="ghost" wire:click="closeCreateDrawer">
                        Cancelar
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ $isSuperAdmin ? 'Crear empresa y admin' : 'Crear empresa' }}
                    </flux:button>
                </div>
            </form>
        </x-side-panel>
    @endif

    @if ($isSuperAdmin)
        <x-side-panel
            wire:model="showDeleteDrawer"
            title="Eliminar empresa"
            subheading="Esta accion elimina la empresa y sus datos operativos. Los usuarios globales no se eliminan."
            labelledby="delete-company-title"
            max-width="max-w-xl"
        >
            <form wire:submit="delete" class="flex flex-1 flex-col">
                <div class="flex-1 space-y-5 p-6">
                    @if ($deletingCompany)
                        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                            <p class="font-semibold">Eliminacion destructiva</p>
                            <p class="mt-2">
                                Se eliminaran los datos asociados a <strong>{{ $deletingCompany->name }}</strong>,
                                incluyendo configuracion, centros, trabajadores, horarios, jornadas, periodos, importaciones
                                y membresias de esa empresa.
                            </p>
                            <p class="mt-2">
                                Si la cuenta cliente queda sin empresas, tambien se eliminara la cuenta cliente.
                            </p>
                        </div>

                        <flux:input
                            wire:model="deleteConfirmation"
                            label="Escribe el nombre de la empresa para confirmar"
                            placeholder="{{ $deletingCompany->name }}"
                            required
                        />
                    @endif
                </div>

                <div class="flex justify-end gap-3 border-t border-zinc-200 p-6 dark:border-zinc-700">
                    <flux:button type="button" variant="ghost" wire:click="closeDeleteDrawer">
                        Cancelar
                    </flux:button>
                    <flux:button type="submit" variant="danger">
                        Eliminar definitivamente
                    </flux:button>
                </div>
            </form>
        </x-side-panel>
    @endif
</section>
