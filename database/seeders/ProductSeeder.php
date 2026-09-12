<?php

namespace Database\Seeders;

use App\Models\CustomerAccount;
use App\Models\CustomerAccountProduct;
use App\Models\Product;
use App\Support\ProductKey;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
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
        ];

        foreach ($products as $key => $data) {
            Product::query()->updateOrCreate(
                ['key' => $key],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'is_addon' => false,
                    'status' => $data['status'],
                    'metadata' => [],
                ],
            );
        }

        $timeProduct = Product::query()->where('key', ProductKey::TIME)->firstOrFail();

        CustomerAccount::query()
            ->orderBy('id')
            ->each(function (CustomerAccount $account) use ($timeProduct): void {
                CustomerAccountProduct::query()->updateOrCreate(
                    [
                        'customer_account_id' => $account->id,
                        'product_id' => $timeProduct->id,
                    ],
                    [
                        'status' => CustomerAccountProduct::STATUS_ACTIVE,
                        'starts_at' => null,
                        'trial_ends_at' => null,
                        'ends_at' => null,
                        'metadata' => [],
                    ],
                );
            });
    }
}
