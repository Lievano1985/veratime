<?php

namespace Database\Seeders;

use App\Domains\Alerts\Support\AlertTypeCatalog;
use App\Models\AlertType;
use Illuminate\Database\Seeder;

class AlertTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AlertTypeCatalog::entries() as $code => $entry) {
            AlertType::query()->updateOrCreate(
                ['code' => $code],
                $entry + ['status' => AlertType::STATUS_ACTIVE],
            );
        }

        AlertType::query()
            ->where('code', 'six_consecutive_days')
            ->update(['status' => AlertType::STATUS_INACTIVE]);
    }
}
