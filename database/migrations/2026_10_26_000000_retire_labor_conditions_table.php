<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('labor_conditions')) {
            if (Schema::hasTable('retired_labor_conditions')) {
                throw new RuntimeException('Ya existe el archivo de condiciones laborales; no se sobrescribirá.');
            }
            Schema::rename('labor_conditions', 'retired_labor_conditions');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('retired_labor_conditions')) {
            if (Schema::hasTable('labor_conditions')) {
                throw new RuntimeException('Ya existe la tabla de condiciones laborales; no se sobrescribirá.');
            }
            Schema::rename('retired_labor_conditions', 'labor_conditions');
        }
    }
};
