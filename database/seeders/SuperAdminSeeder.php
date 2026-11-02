<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn('Este seeder ya no crea accesos. Usa php artisan veratime:create-super-admin desde una terminal interactiva.');
    }
}
