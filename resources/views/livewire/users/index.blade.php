<?php

use App\Domains\Tenancy\Support\CurrentCompany;
use App\Domains\Users\Actions\CreateCompanyUserAction;
use App\Domains\Users\Actions\ResetCompanyUserPasswordAction;
use App\Domains\Users\Actions\UpdateCompanyUserAction;
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

    public function mount(): void
    {
        $this->filters = ['search' => '', 'role_key' => '', 'status' => 'active'];
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

        $validated = $this->validate([
            'editForm.name' => ['required', 'string', 'max:255'],
            'editForm.role_key' => ['required', Rule::in($this->assignableRoleKeys($company))],
            'editForm.user_status' => ['required', Rule::in(['active', 'inactive'])],
            'editForm.membership_status' => ['required', Rule::in(['active', 'inactive'])],
        ])['editForm'];

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
        $status = trim((string) ($this->filters['status'] ?? ''));
        $roleIdsByKey = Role::query()->pluck('id', 'key');

        $users = $company->users()
            ->withPivot(['role_id', 'status', 'is_default'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn ($query) => $query->where('company_user.status', $status))
            ->when($roleKey !== '' && isset($roleIdsByKey[$roleKey]), fn ($query) => $query->where('company_user.role_id', $roleIdsByKey[$roleKey]))
            ->orderBy('users.name')
            ->paginate(15);

        return [
            'users' => $users,
            'roles' => Role::query()->whereIn('key', $this->assignableRoleKeys($company))->orderBy('name')->get(),
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

<section class="w-full space-y-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Usuarios</flux:heading>
            <flux:subheading>Administra accesos, roles y estado de usuarios de la empresa activa.</flux:subheading>
        </div>

        <flux:button type="button" icon="plus" variant="primary" wire:click="openCreatePanel">
            Nuevo usuario
        </flux:button>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    @if ($temporaryPassword)
        <div class="flex flex-col gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="font-semibold">Contraseña temporal</div>
                <div class="font-mono text-base">{{ $temporaryPassword }}</div>
            </div>
            <flux:button type="button" size="sm" variant="ghost" wire:click="clearTemporaryPassword">Ocultar</flux:button>
        </div>
    @endif

    <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
        <div class="grid gap-4 md:grid-cols-3">
            <flux:input label="Buscar" placeholder="Nombre o correo" wire:model.live.debounce.400ms="filters.search" />
            <flux:select label="Rol" wire:model.live="filters.role_key">
                <flux:select.option value="">Todos</flux:select.option>
                @foreach ($roles as $role)
                    <flux:select.option value="{{ $role->key }}">{{ $role->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select label="Estado de acceso" wire:model.live="filters.status">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="active">Activos</flux:select.option>
                <flux:select.option value="inactive">Inactivos</flux:select.option>
            </flux:select>
        </div>
    </section>

    <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Rol</th>
                        <th class="px-4 py-3">Estado usuario</th>
                        <th class="px-4 py-3">Acceso empresa</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse ($users as $user)
                        @php($roleKey = $this->roleKey($user))
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900">{{ $user->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $user->email }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $this->roleLabel($roleKey) }}</td>
                            <td class="px-4 py-3">
                                <x-ui.badge variant="{{ $user->status === 'active' ? 'success' : 'neutral' }}">
                                    {{ $this->statusLabel($user->status) }}
                                </x-ui.badge>
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.badge variant="{{ $user->pivot->status === 'active' ? 'success' : 'neutral' }}">
                                    {{ $this->statusLabel($user->pivot->status) }}
                                </x-ui.badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="openEditPanel({{ $user->id }})">Editar</flux:button>
                                    <flux:button type="button" size="sm" variant="outline" wire:click="openResetPanel({{ $user->id }})">Resetear contraseña</flux:button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-500">
                                No hay usuarios con estos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-zinc-200 px-4 py-3">
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
            <div class="flex justify-end gap-3 border-t border-zinc-200 p-6">
                <flux:button type="button" variant="ghost" wire:click="closeCreatePanel">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Crear usuario</flux:button>
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
                <flux:select label="Estado del usuario" wire:model="editForm.user_status">
                    <flux:select.option value="active">Activo</flux:select.option>
                    <flux:select.option value="inactive">Inactivo</flux:select.option>
                </flux:select>
                <flux:select label="Acceso a esta empresa" wire:model="editForm.membership_status">
                    <flux:select.option value="active">Activo</flux:select.option>
                    <flux:select.option value="inactive">Inactivo</flux:select.option>
                </flux:select>
            </div>
            <div class="flex justify-end gap-3 border-t border-zinc-200 p-6">
                <flux:button type="button" variant="ghost" wire:click="closeEditPanel">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Guardar</flux:button>
            </div>
        </form>
    </x-side-panel>

    <x-side-panel wire:model="showResetPanel" title="Resetear contraseña" subheading="Genera una contraseña temporal para el usuario." labelledby="user-reset-title" max-width="max-w-lg">
        <form wire:submit="resetPassword" class="flex flex-1 flex-col overflow-y-auto">
            <div class="flex-1 space-y-4 p-6">
                <flux:input label="Nueva contraseña temporal" wire:model="resetForm.password" />
                <div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    Comparte esta contraseña por un medio seguro. Vera Time solo la mostrara en esta sesion.
                </div>
            </div>
            <div class="flex justify-end gap-3 border-t border-zinc-200 p-6">
                <flux:button type="button" variant="ghost" wire:click="closeResetPanel">Cancelar</flux:button>
                <flux:button type="submit" variant="primary">Actualizar contraseña</flux:button>
            </div>
        </form>
    </x-side-panel>
</section>
