<?php

namespace App\Domains\Products\Actions;

use App\Models\CustomerAccount;
use App\Models\CustomerAccountProduct;
use App\Models\Product;
use App\Support\ProductKey;

class EnsureCustomerAccountHasProductAction
{
    public function handle(
        CustomerAccount $customerAccount,
        string $productKey = ProductKey::TIME,
        string $status = CustomerAccountProduct::STATUS_ACTIVE,
    ): CustomerAccountProduct {
        $product = $this->resolveProduct($productKey);

        return CustomerAccountProduct::query()->updateOrCreate(
            [
                'customer_account_id' => $customerAccount->id,
                'product_id' => $product->id,
            ],
            [
                'status' => $status,
                'starts_at' => null,
                'trial_ends_at' => null,
                'ends_at' => null,
                'metadata' => [],
            ],
        );
    }

    private function resolveProduct(string $productKey): Product
    {
        $defaults = match ($productKey) {
            ProductKey::TIME => [
                'name' => 'VERA Time',
                'description' => 'Registro, control y calculo de jornadas laborales.',
                'status' => Product::STATUS_ACTIVE,
            ],
            ProductKey::PAYROLL => [
                'name' => 'VERA Payroll',
                'description' => 'Gestion de nomina conectada a los datos de tiempo.',
                'status' => Product::STATUS_DRAFT,
            ],
            ProductKey::RH => [
                'name' => 'VERA RH',
                'description' => 'Gestion de recursos humanos y expediente laboral.',
                'status' => Product::STATUS_DRAFT,
            ],
            default => [
                'name' => $productKey,
                'description' => null,
                'status' => Product::STATUS_DRAFT,
            ],
        };

        return Product::query()->firstOrCreate(
            ['key' => $productKey],
            [
                'name' => $defaults['name'],
                'description' => $defaults['description'],
                'is_addon' => false,
                'status' => $defaults['status'],
                'metadata' => [],
            ],
        );
    }
}
