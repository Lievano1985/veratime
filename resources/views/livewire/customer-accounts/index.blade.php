<?php

use App\Domains\CustomerAccounts\Actions\UpdateCustomerAccountStatusAction;
use App\Domains\Products\Actions\UpdateCustomerAccountProductAction;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountProduct;
use App\Models\Product;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    public array $filters = [];
    public bool $showProductPanel = false;
    public ?int $editingCustomerAccountId = null;
    public string $editingCustomerAccountName = '';
    public array $productForm = [];

    public function mount(): void
    {
        Gate::authorize('viewAny', CustomerAccount::class);

        $this->filters = [
            'search' => '',
            'account_type' => '',
            'status' => '',
        ];

        $this->resetProductForm();
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

    public function openProductPanel(int $customerAccountId): void
    {
        $customerAccount = CustomerAccount::query()
            ->with(['customerAccountProducts.product'])
            ->findOrFail($customerAccountId);

        Gate::authorize('update', $customerAccount);

        $activeProduct = Product::query()
            ->where('status', Product::STATUS_ACTIVE)
            ->orderBy('name')
            ->first();

        $customerProduct = $activeProduct
            ? $customerAccount->customerAccountProducts->firstWhere('product_id', $activeProduct->id)
            : null;

        $this->editingCustomerAccountId = $customerAccount->id;
        $this->editingCustomerAccountName = $customerAccount->name;
        $this->productForm = [
            'product_id' => $activeProduct?->id,
            'status' => $customerProduct?->status ?? CustomerAccountProduct::STATUS_ACTIVE,
            'starts_at' => $customerProduct?->starts_at?->format('Y-m-d') ?? '',
            'trial_ends_at' => $customerProduct?->trial_ends_at?->format('Y-m-d') ?? '',
            'ends_at' => $customerProduct?->ends_at?->format('Y-m-d') ?? '',
        ];
        $this->showProductPanel = true;
    }

    public function updatedProductFormProductId(): void
    {
        if (! $this->editingCustomerAccountId || ! $this->productForm['product_id']) {
            return;
        }

        $customerProduct = CustomerAccountProduct::query()
            ->where('customer_account_id', $this->editingCustomerAccountId)
            ->where('product_id', $this->productForm['product_id'])
            ->first();

        $this->productForm['status'] = $customerProduct?->status ?? CustomerAccountProduct::STATUS_ACTIVE;
        $this->productForm['starts_at'] = $customerProduct?->starts_at?->format('Y-m-d') ?? '';
        $this->productForm['trial_ends_at'] = $customerProduct?->trial_ends_at?->format('Y-m-d') ?? '';
        $this->productForm['ends_at'] = $customerProduct?->ends_at?->format('Y-m-d') ?? '';
    }

    public function saveProduct(UpdateCustomerAccountProductAction $action): void
    {
        $validated = validator($this->productForm, [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('status', Product::STATUS_ACTIVE)],
            'status' => ['required', Rule::in([
                CustomerAccountProduct::STATUS_TRIAL,
                CustomerAccountProduct::STATUS_ACTIVE,
                CustomerAccountProduct::STATUS_PAST_DUE,
                CustomerAccountProduct::STATUS_SUSPENDED,
                CustomerAccountProduct::STATUS_CANCELLED,
            ])],
            'starts_at' => ['nullable', 'date'],
            'trial_ends_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ])->validate();

        $customerAccount = CustomerAccount::query()->findOrFail($this->editingCustomerAccountId);

        $action->handle(auth()->user(), $customerAccount, $validated);

        Session::flash('status', 'Producto contratado actualizado.');
        $this->showProductPanel = false;
        $this->resetProductForm();
        $this->dispatch('companies-updated');
    }

    public function closeProductPanel(): void
    {
        $this->showProductPanel = false;
        $this->resetProductForm();
    }

    public function with(): array
    {
        Gate::authorize('viewAny', CustomerAccount::class);

        $search = trim((string) ($this->filters['search'] ?? ''));
        $accountType = trim((string) ($this->filters['account_type'] ?? ''));
        $status = trim((string) ($this->filters['status'] ?? ''));

        return [
            'products' => Product::query()
                ->where('status', Product::STATUS_ACTIVE)
                ->orderBy('name')
                ->get(),
            'customerAccounts' => CustomerAccount::query()
                ->withCount('companies')
                ->with([
                    'companies' => fn ($query) => $query->orderBy('name'),
                    'customerAccountProducts.product' => fn ($query) => $query->orderBy('name'),
                ])
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

    private function productStatusLabel(string $status): string
    {
        return match ($status) {
            CustomerAccountProduct::STATUS_TRIAL => 'Prueba',
            CustomerAccountProduct::STATUS_PAST_DUE => 'Pago vencido',
            CustomerAccountProduct::STATUS_SUSPENDED => 'Suspendido',
            CustomerAccountProduct::STATUS_CANCELLED => 'Cancelado',
            default => 'Activo',
        };
    }

    private function productStatusBadge(string $status): string
    {
        return match ($status) {
            CustomerAccountProduct::STATUS_TRIAL => 'info',
            CustomerAccountProduct::STATUS_PAST_DUE => 'warning',
            CustomerAccountProduct::STATUS_SUSPENDED => 'warning',
            CustomerAccountProduct::STATUS_CANCELLED => 'danger',
            default => 'success',
        };
    }

    private function resetProductForm(): void
    {
        $this->editingCustomerAccountId = null;
        $this->editingCustomerAccountName = '';
        $this->productForm = [
            'product_id' => null,
            'status' => CustomerAccountProduct::STATUS_ACTIVE,
            'starts_at' => '',
            'trial_ends_at' => '',
            'ends_at' => '',
        ];
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
                        <th class="px-4 py-3">Productos</th>
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
                                <div class="flex flex-col gap-2">
                                    @forelse ($customerAccount->customerAccountProducts as $customerProduct)
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-medium text-zinc-900">{{ $customerProduct->product?->name ?? 'Producto' }}</span>
                                            <x-ui.badge variant="{{ $this->productStatusBadge($customerProduct->status) }}">
                                                {{ $this->productStatusLabel($customerProduct->status) }}
                                            </x-ui.badge>
                                        </div>
                                    @empty
                                        <span class="text-sm text-zinc-500">Sin productos activos</span>
                                    @endforelse
                                    <button type="button" class="btn-ghost btn-sm w-fit" wire:click="openProductPanel({{ $customerAccount->id }})">
                                        Administrar
                                    </button>
                                </div>
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
                                        <button type="button" class="btn-primary btn-sm" wire:click="updateStatus({{ $customerAccount->id }}, 'active')">
                                            Reactivar
                                        </button>
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
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-500">
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

    <x-side-panel wire:model="showProductPanel" title="Productos contratados" subheading="{{ $editingCustomerAccountName }}" labelledby="customer-account-product-title" maxWidth="max-w-xl" closeMethod="closeProductPanel">
        <form wire:submit="saveProduct" class="space-y-5 p-6">
            <flux:select label="Producto" wire:model.live="productForm.product_id">
                @forelse ($products as $product)
                    <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                @empty
                    <flux:select.option value="">No hay productos activos</flux:select.option>
                @endforelse
            </flux:select>

            <flux:select label="Estado del producto" wire:model="productForm.status">
                <flux:select.option value="{{ CustomerAccountProduct::STATUS_TRIAL }}">Prueba</flux:select.option>
                <flux:select.option value="{{ CustomerAccountProduct::STATUS_ACTIVE }}">Activo</flux:select.option>
                <flux:select.option value="{{ CustomerAccountProduct::STATUS_PAST_DUE }}">Pago vencido</flux:select.option>
                <flux:select.option value="{{ CustomerAccountProduct::STATUS_SUSPENDED }}">Suspendido</flux:select.option>
                <flux:select.option value="{{ CustomerAccountProduct::STATUS_CANCELLED }}">Cancelado</flux:select.option>
            </flux:select>

            <div class="grid gap-4 md:grid-cols-3">
                <flux:input type="date" label="Inicio" wire:model="productForm.starts_at" />
                <flux:input type="date" label="Fin de prueba" wire:model="productForm.trial_ends_at" />
                <flux:input type="date" label="Fin efectivo" wire:model="productForm.ends_at" />
            </div>

            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm text-zinc-600">
                Trial, activo y pago vencido permiten operar VERA Time. Suspendido y cancelado bloquean rutas operativas y detienen nuevos procesos automaticos.
            </div>

            <div class="flex justify-end gap-3">
                <button type="button" class="btn-ghost" wire:click="closeProductPanel">Cancelar</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="saveProduct">
                    <span wire:loading wire:target="saveProduct" class="btn-spinner"></span>
                    <span wire:loading.remove wire:target="saveProduct">Guardar producto</span>
                    <span wire:loading wire:target="saveProduct">Guardando</span>
                </button>
            </div>
        </form>
    </x-side-panel>
</section>
