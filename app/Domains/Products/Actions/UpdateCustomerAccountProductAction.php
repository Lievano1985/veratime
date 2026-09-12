<?php

namespace App\Domains\Products\Actions;

use App\Models\CustomerAccount;
use App\Models\CustomerAccountProduct;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateCustomerAccountProductAction
{
    /**
     * @param array{product_id: int, status: string, starts_at?: ?string, trial_ends_at?: ?string, ends_at?: ?string} $data
     */
    public function handle(User $actor, CustomerAccount $customerAccount, array $data): CustomerAccountProduct
    {
        Gate::forUser($actor)->authorize('update', $customerAccount);

        $product = Product::query()
            ->where('status', Product::STATUS_ACTIVE)
            ->find($data['product_id']);

        if (! $product) {
            throw ValidationException::withMessages([
                'productForm.product_id' => 'Selecciona un producto activo valido.',
            ]);
        }

        if (! in_array($data['status'], [
            CustomerAccountProduct::STATUS_TRIAL,
            CustomerAccountProduct::STATUS_ACTIVE,
            CustomerAccountProduct::STATUS_PAST_DUE,
            CustomerAccountProduct::STATUS_SUSPENDED,
            CustomerAccountProduct::STATUS_CANCELLED,
        ], true)) {
            throw ValidationException::withMessages([
                'productForm.status' => 'Selecciona un estado valido para el producto.',
            ]);
        }

        return CustomerAccountProduct::query()->updateOrCreate(
            [
                'customer_account_id' => $customerAccount->id,
                'product_id' => $product->id,
            ],
            [
                'status' => $data['status'],
                'starts_at' => $this->dateOrNull($data['starts_at'] ?? null),
                'trial_ends_at' => $this->dateOrNull($data['trial_ends_at'] ?? null),
                'ends_at' => $this->dateOrNull($data['ends_at'] ?? null),
                'metadata' => [],
            ],
        );
    }

    private function dateOrNull(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->startOfDay();
    }
}
