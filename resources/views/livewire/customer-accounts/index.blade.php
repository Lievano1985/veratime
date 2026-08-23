<?php

use App\Domains\CustomerAccounts\Actions\UpdateCustomerAccountStatusAction;
use App\Models\CustomerAccount;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public array $filters = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', CustomerAccount::class);

        $this->filters = [
            'search' => '',
            'account_type' => '',
            'status' => '',
        ];
    }

    public function updated($property): void
    {
        if (str_starts_with((string) $property, 'filters.')) {
            $this->resetPage();
        }
    }

    public function updateStatus(int $customerAccountId, string $status, UpdateCustomerAccountStatusAction $action): void
    {
        $validated = validator(
            ['status' => $status],
            ['status' => ['required', Rule::in(['active', 'suspended', 'cancelled'])]],
        )->validate();

        $customerAccount = CustomerAccount::query()->findOrFail($customerAccountId);

        Gate::authorize('update', $customerAccount);

        $action->handle(auth()->user(), $customerAccount, $validated['status']);

        Session::flash('status', 'Cuenta cliente actualizada.');
        $this->dispatch('companies-updated');
        $this->resetPage();
    }

    public function with(): array
    {
        Gate::authorize('viewAny', CustomerAccount::class);

        $search = trim((string) ($this->filters['search'] ?? ''));
        $accountType = trim((string) ($this->filters['account_type'] ?? ''));
        $status = trim((string) ($this->filters['status'] ?? ''));

        return [
            'customerAccounts' => CustomerAccount::query()
                ->withCount('companies')
                ->with(['companies' => fn ($query) => $query->orderBy('name')])
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($inner) use ($search): void {
                        $inner
                            ->where('name', 'like', "%{$search}%")
                            ->orWhereHas('companies', function ($companies) use ($search): void {
                                $companies
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('legal_name', 'like', "%{$search}%")
                                    ->orWhere('tax_id', 'like', "%{$search}%");
                            });
                    });
                })
                ->when($accountType !== '', fn ($query) => $query->where('account_type', $accountType))
                ->when($status !== '', fn ($query) => $query->where('status', $status))
                ->orderBy('name')
                ->paginate(10),
        ];
    }

    private function accountTypeLabel(string $accountType): string
    {
        return match ($accountType) {
            'multi_company' => 'Multiempresa',
            default => 'Monoempresa',
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'suspended' => 'Suspendida',
            'cancelled' => 'Cancelada',
            default => 'Activa',
        };
    }

    private function statusBadge(string $status): string
    {
        return match ($status) {
            'suspended' => 'warning',
            'cancelled' => 'danger',
            default => 'success',
        };
    }
}; ?>

<section class="w-full space-y-6 p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <flux:heading size="xl">Cuentas cliente</flux:heading>
            <flux:subheading>Administra el estado comercial de las cuentas que agrupan una o varias empresas.</flux:subheading>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <section class="rounded-lg border border-zinc-200 bg-zinc-50 p-4">
        <div class="grid gap-4 md:grid-cols-3">
            <flux:input label="Buscar" placeholder="Cuenta, empresa o RFC" wire:model.live.debounce.400ms="filters.search" />
            <flux:select label="Tipo" wire:model.live="filters.account_type">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="single_company">Monoempresa</flux:select.option>
                <flux:select.option value="multi_company">Multiempresa</flux:select.option>
            </flux:select>
            <flux:select label="Estado" wire:model.live="filters.status">
                <flux:select.option value="">Todos</flux:select.option>
                <flux:select.option value="active">Activas</flux:select.option>
                <flux:select.option value="suspended">Suspendidas</flux:select.option>
                <flux:select.option value="cancelled">Canceladas</flux:select.option>
            </flux:select>
        </div>
    </section>

    <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-200 text-sm">
                <thead class="bg-zinc-50 text-left text-xs font-medium uppercase text-zinc-500">
                    <tr>
                        <th class="px-4 py-3">Cuenta</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Empresas</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200">
                    @forelse ($customerAccounts as $customerAccount)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900">{{ $customerAccount->name }}</div>
                                <div class="text-xs text-zinc-500">ID {{ $customerAccount->id }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $this->accountTypeLabel($customerAccount->account_type) }}</td>
                            <td class="px-4 py-3">
                                <x-ui.badge variant="{{ $this->statusBadge($customerAccount->status) }}">
                                    {{ $this->statusLabel($customerAccount->status) }}
                                </x-ui.badge>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-sm font-medium text-zinc-900">{{ $customerAccount->companies_count }} empresa(s)</div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @foreach ($customerAccount->companies->take(4) as $company)
                                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-600">{{ $company->name }}</span>
                                    @endforeach
                                    @if ($customerAccount->companies_count > 4)
                                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-600">+{{ $customerAccount->companies_count - 4 }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($customerAccount->status !== 'active')
                                        <flux:button type="button" size="sm" variant="primary" wire:click="updateStatus({{ $customerAccount->id }}, 'active')">
                                            Reactivar
                                        </flux:button>
                                    @endif

                                    @if ($customerAccount->status !== 'suspended')
                                        <flux:button type="button" size="sm" variant="outline" wire:click="updateStatus({{ $customerAccount->id }}, 'suspended')">
                                            Suspender
                                        </flux:button>
                                    @endif

                                    @if ($customerAccount->status !== 'cancelled')
                                        <flux:button type="button" size="sm" variant="danger" wire:click="updateStatus({{ $customerAccount->id }}, 'cancelled')">
                                            Cancelar
                                        </flux:button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-zinc-500">
                                No hay cuentas cliente con estos filtros.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-zinc-200 px-4 py-3">
            {{ $customerAccounts->links() }}
        </div>
    </section>
</section>
