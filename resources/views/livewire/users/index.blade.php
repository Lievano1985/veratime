<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Users\Actions\CreateCompanyUserAction;
use App\Domains\Users\Actions\ResetCompanyUserPasswordAction;
use App\Domains\Users\Actions\UpdateCompanyUserAction;
use App\Domains\Workers\Actions\LinkUserToWorkerAction;
use App\Domains\Workers\Actions\CreateMobileDeviceBindingAuthorizationAction;
use App\Domains\Workers\Actions\RevokeMobileDeviceBindingAction;
use App\Domains\Workers\Actions\RevokeUserWorkerLinkAction;
use App\Models\Company;
use App\Models\MobileDeviceBinding;
use App\Models\Role;
use App\Models\User;
use App\Models\UserWorkerLink;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public array $filters = [];

    public array $form = [];

    public array $editForm = [];

    public array $resetForm = [];

    public bool $showCreatePanel = false;

    public bool $showEditPanel = false;

    public bool $showResetPanel = false;

    public ?int $editingUserId = null;

    public ?int $resettingUserId = null;

    public ?string $temporaryPassword = null;

    public bool $showWorkerLinkPanel = false;

    public ?int $linkingUserId = null;

    public string $linkedWorkerId = '';

    public ?string $mobileDeviceBindingAuthorizationCode = null;

    public function mount(): void
    {
        $this->filters = [
            'search' => '',
            'role_key' => '',
            'user_status' => '',
            'membership_status' => '',
        ];
        $this->form = $this->emptyForm();
        $this->editForm = $this->emptyEditForm();
        $this->resetForm = ['password' => ''];
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.')) {
            $this->resetPage();
        }
    }

    public function openCreatePanel(CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [User::class, $company]);

        $this->form = $this->emptyForm();
        $this->form['password'] = Str::random(12);
        $this->showCreatePanel = true;
    }

    public function create(CreateCompanyUserAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('create', [User::class, $company]);

        $validated = $this->validate([
            'form.name' => ['required', 'string', 'max:255'],
            'form.email' => ['required', 'email', 'max:255'],
            'form.password' => ['required', 'string', 'min:8', 'max:100'],
            'form.role_key' => ['required', Rule::in($this->assignableRoleKeys($company))],
            'form.status' => ['required', Rule::in(['active', 'inactive'])],
        ])['form'];

        $action->handle($company, auth()->user(), $validated);

        $this->temporaryPassword = $validated['password'];
        $this->showCreatePanel = false;
        $this->form = $this->emptyForm();
        $this->resetPage();

        Session::flash('status', 'Usuario creado. Copia la contraseña temporal antes de continuar.');
    }

    public function openEditPanel(int $userId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $user = $this->userForCompany($company, $userId);

        Gate::authorize('update', [$user, $company]);

        $this->editingUserId = $user->id;
        $this->editForm = [
            'name' => $user->name,
            'role_key' => $this->roleKey($user),
            'user_status' => $user->status,
            'membership_status' => (string) $user->pivot->status,
        ];
        $this->showEditPanel = true;
    }

    public function update(UpdateCompanyUserAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $user = $this->userForCompany($company, (int) $this->editingUserId);

        Gate::authorize('update', [$user, $company]);

        $rules = [
            'editForm.name' => ['required', 'string', 'max:255'],
            'editForm.role_key' => ['required', Rule::in($this->assignableRoleKeys($company))],
            'editForm.membership_status' => ['required', Rule::in(['active', 'inactive'])],
        ];

        if (auth()->user()->isSuperAdmin()) {
            $rules['editForm.user_status'] = ['required', Rule::in(['active', 'inactive'])];
        }

        $validated = $this->validate($rules)['editForm'];

        if (! auth()->user()->isSuperAdmin()) {
            $validated['user_status'] = $user->status;
        }

        $action->handle($company, auth()->user(), $user, $validated);

        $this->showEditPanel = false;
        $this->editingUserId = null;

        Session::flash('status', 'Usuario actualizado.');
    }

    public function openResetPanel(int $userId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $user = $this->userForCompany($company, $userId);

        Gate::authorize('resetPassword', [$user, $company]);

        $this->resettingUserId = $user->id;
        $this->resetForm = ['password' => Str::random(12)];
        $this->showResetPanel = true;
    }

    public function resetPassword(ResetCompanyUserPasswordAction $action, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $user = $this->userForCompany($company, (int) $this->resettingUserId);

        Gate::authorize('resetPassword', [$user, $company]);

        $validated = $this->validate([
            'resetForm.password' => ['required', 'string', 'min:8', 'max:100'],
        ])['resetForm'];

        $action->handle($company, auth()->user(), $user, $validated['password']);

        $this->temporaryPassword = $validated['password'];
        $this->showResetPanel = false;
        $this->resettingUserId = null;

        Session::flash('status', 'Contraseña actualizada. Copia la contraseña temporal antes de continuar.');
    }

    public function closeCreatePanel(): void
    {
        $this->showCreatePanel = false;
        $this->form = $this->emptyForm();
        $this->resetValidation('form');
    }

    public function openWorkerLinkPanel(int $userId, CurrentCompany $currentCompany): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $user = $this->userForCompany($company, $userId);
        Gate::authorize('update', [$user, $company]);
        $this->linkingUserId = $user->id;
        $this->linkedWorkerId = (string) (UserWorkerLink::query()->where('company_id', $company->id)->where('user_id', $user->id)->where('status', 'active')->value('worker_id') ?? '');
        $this->mobileDeviceBindingAuthorizationCode = null;
        $this->showWorkerLinkPanel = true;
    }

    public function saveWorkerLink(CurrentCompany $currentCompany, LinkUserToWorkerAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $user = $this->userForCompany($company, (int) $this->linkingUserId);
        Gate::authorize('update', [$user, $company]);
        $this->validate(['linkedWorkerId' => ['required', Rule::exists('workers', 'id')->where('company_id', $company->id)->where('status', 'active')]]);
        try {
            $action->handle($company, $user, Worker::query()->where('company_id', $company->id)->findOrFail($this->linkedWorkerId));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['linkedWorkerId' => $exception->getMessage()]);
        }

        $this->showWorkerLinkPanel = false;
        Session::flash('status', 'Cuenta vinculada con la persona trabajadora.');
    }

    public function revokeWorkerLink(CurrentCompany $currentCompany, RevokeUserWorkerLinkAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $user = $this->userForCompany($company, (int) $this->linkingUserId);
        Gate::authorize('update', [$user, $company]);
        $action->handle($company, $user);
        $this->linkedWorkerId = '';
        Session::flash('status', 'Vínculo revocado; el historial laboral se conserva.');
    }

    public function authorizeMobileDevice(CurrentCompany $currentCompany, CreateMobileDeviceBindingAuthorizationAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $user = $this->userForCompany($company, (int) $this->linkingUserId);
        Gate::authorize('update', [$user, $company]);

        $worker = Worker::query()->where('company_id', $company->id)->where('status', 'active')->find($this->linkedWorkerId);
        if (! $worker) {
            throw ValidationException::withMessages(['linkedWorkerId' => 'Primero selecciona y guarda un trabajador vinculado activo.']);
        }

        try {
            $issued = $action->handle($company, auth()->user(), $user, $worker);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['linkedWorkerId' => $exception->getMessage()]);
        }

        $this->mobileDeviceBindingAuthorizationCode = $issued['authorization_code'];
        Session::flash('status', 'Código de vinculación móvil generado. Se muestra una sola vez y vence en 15 minutos.');
    }

    public function revokeMobileDevice(int $bindingId, CurrentCompany $currentCompany, RevokeMobileDeviceBindingAction $action): void
    {
        $company = $this->currentCompanyOrFail($currentCompany);
        $user = $this->userForCompany($company, (int) $this->linkingUserId);
        Gate::authorize('update', [$user, $company]);
        $binding = MobileDeviceBinding::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('worker_id', $this->linkedWorkerId)
            ->findOrFail($bindingId);

        try {
            $action->handle($binding, auth()->user());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['linkedWorkerId' => $exception->getMessage()]);
        }

        Session::flash('status', 'Dispositivo móvil revocado. Ya no podrá registrar marcajes cuando la política lo requiera.');
    }

    public function closeEditPanel(): void
    {
        $this->showEditPanel = false;
        $this->editingUserId = null;
        $this->editForm = $this->emptyEditForm();
        $this->resetValidation('editForm');
    }

    public function closeResetPanel(): void
    {
        $this->showResetPanel = false;
        $this->resettingUserId = null;
        $this->resetForm = ['password' => ''];
        $this->resetValidation('resetForm');
    }

    public function clearTemporaryPassword(): void
    {
        $this->temporaryPassword = null;
    }

    public function with(CurrentCompany $currentCompany): array
    {
        $company = $this->currentCompanyOrFail($currentCompany);

        Gate::authorize('viewAny', [User::class, $company]);

        $search = trim((string) ($this->filters['search'] ?? ''));
        $roleKey = trim((string) ($this->filters['role_key'] ?? ''));
        $userStatus = trim((string) ($this->filters['user_status'] ?? ''));
        $membershipStatus = trim((string) ($this->filters['membership_status'] ?? ''));
        $roleIdsByKey = Role::query()->pluck('id', 'key');

        $users = $company->users()
            ->with(['workerLinks' => fn ($query) => $query->where('company_id', $company->id)->where('status', 'active')->with('worker')])
            ->withPivot(['role_id', 'status', 'is_default'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            })
            ->when($userStatus !== '', fn ($query) => $query->where('users.status', $userStatus))
            ->when($membershipStatus !== '', fn ($query) => $query->where('company_user.status', $membershipStatus))
            ->when($roleKey !== '' && isset($roleIdsByKey[$roleKey]), fn ($query) => $query->where('company_user.role_id', $roleIdsByKey[$roleKey]))
            ->orderBy('users.name')
            ->paginate(15);

        return [
            'users' => $users,
            'roles' => Role::query()->whereIn('key', $this->assignableRoleKeys($company))->orderBy('name')->get(),
            'isSuperAdmin' => auth()->user()->isSuperAdmin(),
            'linkWorkers' => $company->workers()->where('status', 'active')->orderBy('full_name')->get(),
            'mobileDeviceBindings' => $this->linkingUserId && $this->linkedWorkerId
                ? MobileDeviceBinding::query()
                    ->where('company_id', $company->id)
                    ->where('user_id', $this->linkingUserId)
                    ->where('worker_id', $this->linkedWorkerId)
                    ->with('revokedBy')
                    ->latest('activated_at')
                    ->get()
                : collect(),
        ];
    }

    private function currentCompanyOrFail(CurrentCompany $currentCompany): Company
    {
        $company = $currentCompany->get();

        abort_unless($company, 403);

        return $company;
    }

    private function userForCompany(Company $company, int $userId): User
    {
        return $company->users()
            ->withPivot(['role_id', 'status', 'is_default'])
            ->findOrFail($userId);
    }

    private function roleKey(User $user): ?string
    {
        return Role::query()->whereKey($user->pivot->role_id)->value('key');
    }

    /**
     * @return list<string>
     */
    private function assignableRoleKeys(Company $company): array
    {
        return match (auth()->user()->roleKeyForCompany($company)) {
            RoleKey::SUPER_ADMIN => RoleKey::companyRoleKeys(),
            RoleKey::ADMIN_EMPRESA => [
                RoleKey::ADMIN_EMPRESA,
                RoleKey::RH_ADMIN,
                RoleKey::RH_OPERATIVO,
                RoleKey::SUPERVISOR,
                RoleKey::TRABAJADOR,
            ],
            RoleKey::RH_ADMIN => [
                RoleKey::RH_OPERATIVO,
                RoleKey::SUPERVISOR,
                RoleKey::TRABAJADOR,
            ],
            default => [],
        };
    }

    private function roleLabel(?string $roleKey): string
    {
        return match ($roleKey) {
            RoleKey::SUPER_ADMIN => 'Super administrador',
            RoleKey::ADMIN_EMPRESA => 'Administrador de empresa',
            RoleKey::RH_ADMIN => 'RH administrador',
            RoleKey::RH_OPERATIVO => 'RH operativo',
            RoleKey::SUPERVISOR => 'Supervisor',
            RoleKey::TRABAJADOR => 'Trabajador',
            default => 'Sin rol',
        };
    }

    private function statusLabel(string $status): string
    {
        return $status === 'active' ? 'Activo' : 'Inactivo';
    }

    private function emptyForm(): array
    {
        return [
            'name' => '',
            'email' => '',
            'password' => '',
            'role_key' => RoleKey::RH_OPERATIVO,
            'status' => 'active',
        ];
    }

    private function emptyEditForm(): array
    {
        return [
            'name' => '',
            'role_key' => RoleKey::RH_OPERATIVO,
            'user_status' => 'active',
            'membership_status' => 'active',
        ];
    }
}; ?>

<div>
<section class="w-full space-y-6 bg-surface-bg p-6 text-surface-text">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold text-brand-navy">Usuarios</h1>
            <p class="mt-1.5 text-[13.5px] text-surface-muted">Administra usuarios, roles y membresías de la empresa activa.</p>
        </div>

        <button type="button" class="btn-primary" wire:click="openCreatePanel">
            <span class="text-base leading-none">+</span>
            Nuevo usuario
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-xl border border-status-rest-line bg-status-rest-bg px-4 py-3 text-sm font-medium text-status-rest-text">
            {{ session('status') }}
        </div>
    @endif

    @if ($temporaryPassword)
        <div class="flex flex-col gap-3 rounded-xl border border-status-warn-line bg-status-warn-bg px-4 py-3 text-sm text-status-warn-text sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="font-semibold">Contraseña temporal</div>
                <div class="font-mono text-base">{{ $temporaryPassword }}</div>
            </div>
            <button type="button" class="btn-ghost btn-sm" wire:click="clearTemporaryPassword">Ocultar</button>
        </div>
    @endif

    <section class="rounded-2xl border border-surface-line bg-surface-card p-5 shadow-[0_20px_50px_-34px_rgba(2,25,57,0.22)]">
        <div class="grid gap-4 md:grid-cols-4">
            <flux:input label="Buscar" placeholder="Nombre o correo" wire:model.live.debounce.400ms="filters.search" />
            <flux:select label="Rol" wire:model.live="filters.role_key">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($roles as $role)
                    <flux:select.option value="{{ $role->key }}">{{ $role->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select label="Estado usuario" wire:model.live="filters.user_status">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="active">Activos</flux:select.option>
                <flux:select.option value="inactive">Inactivos</flux:select.option>
            </flux:select>
            <flux:select label="Acceso empresa" wire:model.live="filters.membership_status">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="active">Activos</flux:select.option>
                <flux:select.option value="inactive">Inactivos</flux:select.option>
            </flux:select>
        </div>
    </section>

    <section class="table-wrap">
        <div>
            <table class="w-full min-w-[900px] border-collapse">
                <thead>
                    <tr>
                        <th class="table-head-cell">Usuario</th>
                        <th class="table-head-cell">Rol</th>
                        <th class="table-head-cell">Estado usuario</th>
                        <th class="table-head-cell">Acceso empresa</th>
                        <th class="table-head-cell">Trabajador vinculado</th>
                        <th class="table-head-cell text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        @php($roleKey = $this->roleKey($user))
                        <tr class="table-row">
                            <td class="table-cell">
                                <div class="font-semibold text-brand-navy">{{ $user->name }}</div>
                                <div class="text-xs text-surface-muted">{{ $user->email }}</div>
                            </td>
                            <td class="table-cell">{{ $user->workerLinks->first()?->worker?->full_name ?? 'Sin vínculo' }}</td>
                            <td class="table-cell">{{ $this->roleLabel($roleKey) }}</td>
                            <td class="table-cell">
                                <x-ui.badge variant="{{ $user->status === 'active' ? 'success' : 'neutral' }}">
                                    {{ $this->statusLabel($user->status) }}
                                </x-ui.badge>
                            </td>
                            <td class="table-cell">
                                <x-ui.badge variant="{{ $user->pivot->status === 'active' ? 'success' : 'neutral' }}">
                                    {{ $this->statusLabel($user->pivot->status) }}
                                </x-ui.badge>
                            </td>
                            <td class="table-cell text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <button type="button" class="btn-icon" wire:click="openEditPanel({{ $user->id }})" aria-label="Editar usuario" title="Editar">
                                        <svg class="h-4 w-4 text-surface-muted" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                    <button type="button" class="btn-icon" wire:click="openWorkerLinkPanel({{ $user->id }})" aria-label="Vincular trabajador" title="Vincular trabajador">↔</button>
                                    <button type="button" class="btn-icon" wire:click="openResetPanel({{ $user->id }})" aria-label="Resetear contraseña" title="Resetear contraseña">
                                        <svg class="h-4 w-4 text-brand-blue" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 4v6h6M20 20v-6h-6M20 9a8 8 0 0 0-13.5-3.5L4 8m16 8-2.5 2.5A8 8 0 0 1 4 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="table-cell py-8 text-center text-surface-muted">
                                No hay usuarios con estos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-surface-line px-4 py-3">
            {{ $users->links() }}
        </div>
    </section>

    <x-side-panel wire:model="showCreatePanel" title="Nuevo usuario" subheading="Crea un acceso para la empresa activa." labelledby="user-create-title" max-width="max-w-xl">
        <form wire:submit="create" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <flux:input label="Nombre" wire:model="form.name" />
                <flux:input type="email" label="Correo" wire:model="form.email" />
                <flux:input label="Contraseña temporal" wire:model="form.password" />
                <flux:select label="Rol" wire:model="form.role_key">
                    @foreach ($roles as $role)
                        <flux:select.option value="{{ $role->key }}">{{ $role->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select label="Estado inicial" wire:model="form.status">
                    <flux:select.option value="active">Activo</flux:select.option>
                    <flux:select.option value="inactive">Inactivo</flux:select.option>
                </flux:select>
            </div>
            <div class="flex justify-end gap-3 border-t border-surface-line p-6">
                <button type="button" class="btn-ghost" wire:click="closeCreatePanel">Cancelar</button>
                <button type="submit" class="btn-primary">Crear usuario</button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showEditPanel" title="Editar usuario" subheading="Actualiza datos, rol y acceso en la empresa activa." labelledby="user-edit-title" max-width="max-w-xl">
        <form wire:submit="update" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <flux:input label="Nombre" wire:model="editForm.name" />
                <flux:select label="Rol" wire:model="editForm.role_key">
                    @foreach ($roles as $role)
                        <flux:select.option value="{{ $role->key }}">{{ $role->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($isSuperAdmin)
                    <flux:select label="Estado global del usuario" wire:model="editForm.user_status">
                        <flux:select.option value="active">Activo</flux:select.option>
                        <flux:select.option value="inactive">Inactivo</flux:select.option>
                    </flux:select>
                @else
                    <div>
                        <div class="form-label">Estado global del usuario</div>
                        <x-ui.badge variant="{{ ($editForm['user_status'] ?? 'active') === 'active' ? 'success' : 'neutral' }}">
                            {{ $this->statusLabel($editForm['user_status'] ?? 'active') }}
                        </x-ui.badge>
                        <p class="form-hint">Solo el super administrador puede cambiar el estado global.</p>
                    </div>
                @endif
                <flux:select label="Acceso a esta empresa" wire:model="editForm.membership_status">
                    <flux:select.option value="active">Activo</flux:select.option>
                    <flux:select.option value="inactive">Inactivo</flux:select.option>
                </flux:select>
            </div>
            <div class="flex justify-end gap-3 border-t border-surface-line p-6">
                <button type="button" class="btn-ghost" wire:click="closeEditPanel">Cancelar</button>
                <button type="submit" class="btn-primary">Guardar</button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showResetPanel" title="Resetear contraseña" subheading="Genera una contraseña temporal para el usuario." labelledby="user-reset-title" max-width="max-w-lg">
        <form wire:submit="resetPassword" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <flux:input label="Nueva contraseña temporal" wire:model="resetForm.password" />
                <div class="rounded-xl border border-status-warn-line bg-status-warn-bg px-3 py-2 text-sm text-status-warn-text">
                    Comparte esta contraseña por un medio seguro. Vera Time solo la mostrará en esta sesión.
                </div>
            </div>
            <div class="flex justify-end gap-3 border-t border-surface-line p-6">
                <button type="button" class="btn-ghost" wire:click="closeResetPanel">Cancelar</button>
                <button type="submit" class="btn-primary">Actualizar contraseña</button>
            </div>
        </form>
    </x-side-panel>
</section>

<x-side-panel wire:model="showWorkerLinkPanel" title="Vínculo con trabajador" subheading="Acceso personal web/PWA; no modifica el kiosco." max-width="max-w-lg">
    <form wire:submit="saveWorkerLink" class="flex flex-1 flex-col">
        <div class="flex-1 space-y-4 p-6">
            <flux:select label="Trabajador activo" wire:model="linkedWorkerId">
                <flux:select.option value="">Seleccionar</flux:select.option>
                @foreach ($linkWorkers as $worker)
                    <flux:select.option value="{{ $worker->id }}">{{ $worker->employee_code }} — {{ $worker->full_name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($linkedWorkerId)
                <div class="rounded-xl border border-surface-line bg-surface-bg p-4">
                    <p class="text-sm font-semibold text-brand-navy">Dispositivo móvil</p>
                    <p class="mt-1 text-xs text-surface-muted">Genera un código de un solo uso para que esta persona vincule su teléfono. Vence en 15 minutos.</p>
                    <button type="button" wire:click="authorizeMobileDevice" class="btn-secondary mt-3">Generar código de vinculación</button>

                    @if ($mobileDeviceBindingAuthorizationCode)
                        <div class="mt-3 rounded-lg border border-status-warn-line bg-status-warn-bg p-3">
                            <p class="text-xs font-semibold text-status-warn-text">Comparte este código de forma segura. No volverá a mostrarse al cerrar este panel.</p>
                            <code class="mt-2 block break-all rounded bg-white px-3 py-2 font-mono text-sm font-bold text-brand-navy">{{ $mobileDeviceBindingAuthorizationCode }}</code>
                        </div>
                    @endif

                    <div class="mt-4 border-t border-surface-line pt-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-surface-muted">Dispositivos vinculados</p>
                        <div class="mt-2 space-y-2">
                            @forelse ($mobileDeviceBindings as $binding)
                                <div wire:key="mobile-device-binding-{{ $binding->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-surface-line bg-white px-3 py-2">
                                    <div>
                                        <p class="text-sm font-semibold text-brand-navy">{{ $binding->device_name }}</p>
                                        <p class="text-xs text-surface-muted">Activado {{ $binding->activated_at?->timezone($currentCompany->timezone)->format('d/m/Y H:i') }}</p>
                                        @if ($binding->status === 'revoked')
                                            <p class="mt-1 text-xs text-surface-muted">Revocado {{ $binding->revoked_at?->timezone($currentCompany->timezone)->format('d/m/Y H:i') }}{{ $binding->revokedBy ? ' por '.$binding->revokedBy->name : '' }}</p>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-ui.badge variant="{{ $binding->status === 'active' ? 'success' : 'danger' }}">{{ $binding->status === 'active' ? 'Autorizado' : 'Revocado' }}</x-ui.badge>
                                        @if ($binding->status === 'active')
                                            <button type="button" wire:click="revokeMobileDevice({{ $binding->id }})" wire:confirm="Este teléfono ya no podrá usarse para marcajes que exijan dispositivo autorizado. ¿Continuar?" class="btn-danger btn-sm">Revocar</button>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-surface-muted">Aún no hay dispositivos móviles vinculados.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif
        </div>
        <div class="flex justify-between border-t border-surface-line p-6"><button type="button" class="btn-ghost" wire:click="revokeWorkerLink" wire:confirm="¿Revocar el vínculo?">Revocar vínculo</button><button type="submit" class="btn-primary">Guardar vínculo</button></div>
    </form>
</x-side-panel>
</div>
