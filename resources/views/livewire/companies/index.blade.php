<?php

use App\Domains\Companies\Actions\CreateCompanyAction;
use App\Domains\Companies\Actions\CreateTenantWithAdminAction;
use App\Domains\Companies\Actions\DeleteCompanyAction;
use App\Domains\Companies\Actions\AssessCompanyDemoScenarioEligibilityAction;
use App\Domains\Companies\Actions\RequestCompanyDemoScenarioAction;
use App\Domains\Companies\Actions\UpdateCompanyAction;
use App\Domains\Tenancy\Support\CurrentCompany;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
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
            $existingAdministrator = User::query()
                ->where('email', Str::lower(trim((string) data_get($this->createForm, 'admin_email'))))
                ->first();

            $rules += [
                'createForm.account_type' => ['required', Rule::in(['single_company', 'multi_company'])],
                'createForm.admin_name' => ['required', 'string', 'max:255'],
                'createForm.admin_email' => ['required', 'email', 'max:255'],
                'createForm.admin_password' => [$existingAdministrator ? 'nullable' : 'required', 'string', 'min:8', 'max:100', 'regex:/^(?=.*[A-Z])(?=.*\\d)(?=.*[^A-Za-z0-9]).+$/'],
                'createForm.admin_status' => ['required', Rule::in(['active', 'inactive'])],
            ];
        }

        $validated = $this->validate($rules)['createForm'];

        if (auth()->user()->isSuperAdmin()) {
            $tenantCreation = $createTenant->handle(auth()->user(), [
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

            $company = $tenantCreation->company;
            $this->temporaryPassword = $tenantCreation->reusedExistingAdministrator ? null : $validated['admin_password'];

            if ($company->status === 'active') {
                session(['current_company_id' => $company->id]);
                app(CurrentCompany::class)->set($company);
            }

            $this->dispatch('companies-updated');
            Session::flash('status', $tenantCreation->reusedExistingAdministrator
                ? 'Empresa creada y usuario existente asignado como administrador. Su contraseña no fue modificada.'
                : 'Empresa y administrador principal creados. Copia la contraseña temporal antes de continuar.');
        } else {
            $company = $createCompany->handle(auth()->user(), $validated);
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

    public function requestDemo(int $companyId, RequestCompanyDemoScenarioAction $action): void
    {
        $company = $this->authorizedCompany($companyId, 'update');

        $scenario = $action->handle($company, auth()->user());

        Session::flash('status', match ($scenario->status) {
            \App\Models\CompanyDemoScenario::STATUS_COMPLETED => 'El escenario demo se creó correctamente.',
            \App\Models\CompanyDemoScenario::STATUS_FAILED => 'No se pudo crear el escenario demo. Revisa el detalle mostrado en esta sección e inténtalo nuevamente.',
            default => 'El escenario demo ya se está preparando.',
        });
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

    public function with(CurrentCompany $currentCompany, AssessCompanyDemoScenarioEligibilityAction $assessDemo): array
    {
        $company = $currentCompany->get();
        $company?->loadMissing('demoScenario');
        $canManageCurrentCompany = $company ? Gate::allows('update', $company) : false;

        return [
            'companies' => $this->companyList(),
            'singleCompanySummary' => $this->singleCompanySummary($company),
            'currentCompany' => $company,
            'currentDemoScenario' => $company?->demoScenario,
            'editingDemoEligibility' => $this->editingCompanyId
                ? $assessDemo->handle(Company::query()->findOrFail($this->editingCompanyId))
                : null,
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

<section class="w-full space-y-8 bg-surface-bg p-6 text-surface-text">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-brand-navy">Empresas</h1>
            <p class="mt-1.5 text-[13.5px] text-surface-muted">Administra empresas y datos generales. La configuración operativa vive en Configuración de empresa.</p>
        </div>

        @if ($canCreateCompany)
            <button type="button" class="btn-primary" wire:click="openCreateDrawer">
                <span class="text-base leading-none">+</span>
                Nueva empresa
            </button>
        @endif
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-status-rest-line bg-status-rest-bg px-4 py-3 text-sm font-medium text-status-rest-text">
            {{ session('status') }}
        </div>
    @endif

    @if ($currentDemoScenario)
        @php($demoStatus = $currentDemoScenario->status)
        <section class="rounded-2xl border p-4 {{ $demoStatus === 'completed' ? 'border-status-rest-line bg-status-rest-bg text-status-rest-text' : ($demoStatus === 'failed' ? 'border-status-pending-line bg-status-pending-bg text-status-pending-text' : 'border-brand-primary/20 bg-brand-primary/5 text-brand-navy') }}">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold">Escenario de demostracion</p>
                    @if ($demoStatus === 'completed')
                        <p class="text-xs">Listo: 10 trabajadores, dos semanas de programacion y periodos de asistencia preparados. El Centro Nocturno puede validarse, cerrarse y exportarse; el Diurno conserva alertas de prueba.</p>
                    @elseif ($demoStatus === 'failed')
                        <p class="text-xs">No se pudo terminar de preparar el demo. Revisa el registro tecnico antes de operar la empresa.</p>
                    @else
                        <p class="text-xs">Preparando horarios, marcajes, incidencias y jornadas. Actualiza esta pagina en unos momentos.</p>
                    @endif
                </div>
                <span class="text-xs font-semibold uppercase tracking-wide">{{ $demoStatus }}</span>
            </div>
        </section>
    @endif

    @if ($temporaryPassword)
        <div class="flex flex-col gap-3 rounded-xl border border-status-warn-line bg-status-warn-bg px-4 py-3 text-sm text-status-warn-text sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="font-semibold">Contraseña temporal del administrador principal</div>
                <div class="font-mono text-base">{{ $temporaryPassword }}</div>
            </div>
            <button type="button" class="btn-ghost btn-sm" wire:click="clearTemporaryPassword">Ocultar</button>
        </div>
    @endif

    <div class="space-y-6">
        @if (! $showCompanyDirectory && $singleCompanySummary)
            <section class="rounded-2xl border border-surface-line bg-surface-card p-6 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-display text-lg font-bold text-brand-navy">Empresa actual</h2>
                        <p class="mt-2 text-base font-semibold text-surface-text">{{ $singleCompanySummary->name }}</p>
                        <p class="text-sm text-surface-muted">{{ $singleCompanySummary->tax_id ?: 'Sin RFC' }}</p>
                    </div>

                    <span class="{{ $singleCompanySummary->status === 'active' ? 'badge-success' : 'badge-muted' }}">
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        {{ $singleCompanySummary->status }}
                    </span>
                </div>
            </section>
        @endif

        @if ($showCompanyDirectory)
            <section class="rounded-2xl border border-surface-line bg-surface-card p-6 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
                <div class="mb-4">
                    <h2 class="font-display text-lg font-bold text-brand-navy">Empresas autorizadas</h2>
                    <p class="mt-1 text-[13px] text-surface-muted">Se listan las empresas disponibles para administración. Las inactivas no aparecen en el selector operativo.</p>
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

                <div class="table-wrap">
                    <div class="max-h-[360px] overflow-y-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr>
                                    <th class="table-head-cell">Empresa</th>
                                    <th class="table-head-cell">Administrador</th>
                                    <th class="table-head-cell">Estado</th>
                                    <th class="table-head-cell text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($companies as $company)
                                    @php($principalAdmin = $company->users->first())

                                    <tr class="table-row">
                                        <td class="table-cell">
                                            <div class="font-semibold text-brand-navy">{{ $company->name }}</div>
                                            <div class="text-xs text-surface-muted">{{ $company->tax_id ?: 'Sin RFC' }}</div>
                                        </td>
                                        <td class="table-cell text-surface-muted">
                                            {{ $principalAdmin?->email ?: 'Sin administrador principal activo' }}
                                        </td>
                                        <td class="table-cell">
                                            <span class="{{ $company->status === 'active' ? 'badge-success' : 'badge-muted' }}">
                                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                                {{ $company->status }}
                                            </span>
                                        </td>
                                        <td class="table-cell">
                                            <div class="flex items-center justify-end gap-1.5">
                                                @can('update', $company)
                                                    <button type="button" class="btn-icon" wire:click="loadEditForm({{ $company->id }})" aria-label="Editar empresa" title="Editar">
                                                        <svg class="h-4 w-4 text-surface-muted" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </button>
                                                @endcan

                                                @can('delete', $company)
                                                    <button type="button" class="btn-icon" wire:click="openDeleteDrawer({{ $company->id }})" aria-label="Eliminar empresa" title="Eliminar">
                                                        <svg class="h-4 w-4 text-status-pending-text" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0-1 14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2L4 6h16Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </button>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="table-cell py-8 text-center text-surface-muted">
                                            No hay empresas que coincidan con los filtros.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-4">
                    {{ $companies->links() }}
                </div>
            </section>
        @endif

            @if ($editingCompanyId && $canManageEditingCompany)
                <section class="rounded-2xl border border-surface-line bg-surface-card p-6 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
                    <div class="mb-4">
                        <h2 class="font-display text-lg font-bold text-brand-navy">Datos basicos</h2>
                        <p class="mt-1 text-[13px] text-surface-muted">Editar informacion general y estado operativo de la empresa.</p>
                    </div>

                    <form wire:submit="update" class="space-y-4">
                        <flux:input wire:model="editForm.name" label="Nombre comercial" required />
                        <flux:input wire:model="editForm.legal_name" label="Razón social" />
                        <flux:input wire:model="editForm.tax_id" label="RFC" />
                        <flux:input wire:model="editForm.timezone" label="Zona horaria" required />

                        <div>
                            <label class="form-label">Estado</label>
                            <x-ui.select wire:model="editForm.status">
                                <option value="active">Activa</option>
                                <option value="inactive">Inactiva</option>
                                @if ($isSuperAdmin)
                                    <option value="suspended">Suspendida</option>
                                    <option value="cancelled">Cancelada</option>
                                @endif
                            </x-ui.select>
                            @error('editForm.status')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        @if ($isSuperAdmin)
                            <flux:select wire:model="editForm.account_type" label="Tipo de cuenta cliente">
                                <flux:select.option value="single_company">Monoempresa</flux:select.option>
                                <flux:select.option value="multi_company">Multiempresa</flux:select.option>
                            </flux:select>
                        @endif

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <button type="submit" class="btn-primary">Guardar empresa</button>

                            @if ($isSuperAdmin)
                                <button type="button" class="btn-danger" wire:click="openDeleteDrawer({{ $editingCompanyId }})">
                                    Eliminar empresa
                                </button>
                            @endif
                        </div>
                    </form>

                    <section class="mt-6 rounded-2xl border border-brand-primary/20 bg-brand-primary/5 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-semibold text-brand-navy">Escenario de demostracion</h3>
                                @if (data_get($editingDemoEligibility, 'allowed'))
                                    <p class="mt-1 text-xs leading-5 text-surface-muted">Genera datos ficticios completos para validar centros, horarios, jornadas, alertas, incidencias y exportaciones.</p>
                                @else
                                    <p class="mt-1 text-xs leading-5 text-surface-muted">{{ data_get($editingDemoEligibility, 'reason') }}</p>
                                @endif

                                @if (data_get($editingDemoEligibility, 'scenario.status') === \App\Models\CompanyDemoScenario::STATUS_FAILED)
                                    <p class="mt-2 text-xs leading-5 text-status-danger-text">La preparación anterior no concluyó: {{ data_get($editingDemoEligibility, 'scenario.error_message') ?: 'sin detalle disponible' }}</p>
                                @endif
                            </div>

                            @if (data_get($editingDemoEligibility, 'allowed'))
                                <flux:modal.trigger name="confirm-company-demo-generation">
                                    <button
                                        type="button"
                                        class="btn-primary shrink-0"
                                        x-data=""
                                        x-on:click.prevent="$dispatch('open-modal', 'confirm-company-demo-generation')"
                                    >
                                        Generar escenario demo
                                    </button>
                                </flux:modal.trigger>
                            @endif
                        </div>
                    </section>

                    @if (data_get($editingDemoEligibility, 'allowed'))
                        <flux:modal name="confirm-company-demo-generation" focusable class="max-w-lg">
                            <div class="space-y-5 p-6">
                                <div class="space-y-2">
                                    <flux:heading size="lg">Preparar escenario de demostración</flux:heading>
                                    <flux:subheading>
                                        Se crearán datos ficticios de centros, trabajadores, horarios, marcajes, incidencias, jornadas, alertas y periodos. Esta operación sólo puede realizarse una vez por empresa.
                                    </flux:subheading>
                                </div>

                                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                                    <flux:modal.close>
                                        <button type="button" class="btn-secondary w-full sm:w-auto">Cancelar</button>
                                    </flux:modal.close>

                                    <button
                                        type="button"
                                        class="btn-primary w-full sm:w-auto"
                                        wire:click="requestDemo({{ $editingCompanyId }})"
                                        wire:target="requestDemo"
                                        wire:loading.attr="disabled"
                                        x-data=""
                                        x-on:click="$dispatch('close-modal', 'confirm-company-demo-generation')"
                                    >
                                        <span wire:loading.remove wire:target="requestDemo">Sí, generar demo</span>
                                        <span wire:loading wire:target="requestDemo">Preparando escenario...</span>
                                    </button>
                                </div>
                            </div>
                        </flux:modal>
                    @endif
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
                    <section class="space-y-4 rounded-2xl border border-surface-line bg-surface-card p-4">
                        <div>
                            <h3 class="font-display text-sm font-bold text-brand-navy">Datos de empresa</h3>
                            <p class="text-xs text-surface-muted">Información base del tenant operativo.</p>
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
                            <p class="form-hint">Define si esta cuenta cliente operara una sola empresa o podra agrupar varias empresas bajo la misma cuenta.</p>
                        @endif
                    </section>

                    @if ($isSuperAdmin)
                        <section class="space-y-4 rounded-2xl border border-surface-line bg-surface-card p-4">
                            <div>
                                <h3 class="font-display text-sm font-bold text-brand-navy">Administrador principal</h3>
                                <p class="text-xs text-surface-muted">Este usuario quedara como administrador general de la empresa. El super admin no se agrega como miembro operativo.</p>
                            </div>
                            <flux:input wire:model="createForm.admin_name" label="Nombre" required />
                            <flux:input wire:model="createForm.admin_email" type="email" label="Correo" required />
                            <flux:input wire:model="createForm.admin_password" label="Contraseña temporal (sólo para usuario nuevo)" />
                            <p class="form-hint">Si el correo ya corresponde a una cuenta sin empresas, se reutiliza y esta contraseña no se modifica. Para un usuario nuevo debe tener mínimo 8 caracteres, una mayúscula, un número y un símbolo.</p>
                            <flux:select wire:model="createForm.admin_status" label="Estado inicial del usuario">
                                <flux:select.option value="active">Activo</flux:select.option>
                                <flux:select.option value="inactive">Inactivo</flux:select.option>
                            </flux:select>
                        </section>
                    @endif
                </div>

                <div class="flex justify-end gap-3 border-t border-surface-line p-6">
                    <button type="button" class="btn-ghost" wire:click="closeCreateDrawer">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-primary">
                        {{ $isSuperAdmin ? 'Crear empresa y admin' : 'Crear empresa' }}
                    </button>
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
                        <div class="rounded-2xl border border-status-pending-line bg-status-pending-bg p-4 text-sm text-status-pending-text">
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

                <div class="flex justify-end gap-3 border-t border-surface-line p-6">
                    <button type="button" class="btn-ghost" wire:click="closeDeleteDrawer">
                        Cancelar
                    </button>
                    <button type="submit" class="btn-danger">
                        Eliminar definitivamente
                    </button>
                </div>
            </form>
        </x-side-panel>
    @endif
</section>
