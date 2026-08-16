<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\RoleKey;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['key' => RoleKey::SUPER_ADMIN, 'name' => 'Super administrador', 'description' => 'Administra la plataforma, tenants y catalogos globales.'],
            ['key' => RoleKey::ADMIN_EMPRESA, 'name' => 'Administrador de empresa', 'description' => 'Administra configuracion, usuarios y operacion completa de la empresa.'],
            ['key' => RoleKey::RH_ADMIN, 'name' => 'RH administrador', 'description' => 'Administra operacion RH, usuarios RH operativos, supervisores y alcances.'],
            ['key' => RoleKey::RH_OPERATIVO, 'name' => 'RH operativo', 'description' => 'Opera asistencia y tiempo dentro de centros o unidades asignadas.'],
            ['key' => RoleKey::SUPERVISOR, 'name' => 'Supervisor', 'description' => 'Consulta equipos con alcance explicito.'],
            ['key' => RoleKey::TRABAJADOR, 'name' => 'Trabajador', 'description' => 'Acceso futuro al portal de la persona trabajadora.'],
        ])->each(fn (array $role) => Role::query()->updateOrCreate(
            ['key' => $role['key']],
            $role + ['is_system' => true],
        ));
    }
}
