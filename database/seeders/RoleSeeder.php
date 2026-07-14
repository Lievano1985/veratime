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
            ['key' => RoleKey::OWNER, 'name' => 'Propietario', 'description' => 'Control inicial de la empresa.'],
            ['key' => RoleKey::ADMIN, 'name' => 'Administrador', 'description' => 'Administra configuracion y usuarios.'],
            ['key' => RoleKey::RH, 'name' => 'Recursos Humanos', 'description' => 'Opera trabajadores, jornadas e incidencias.'],
            ['key' => RoleKey::SUPERVISOR, 'name' => 'Supervisor', 'description' => 'Consulta equipos con alcance explicito futuro.'],
            ['key' => RoleKey::PAYROLL, 'name' => 'Nomina', 'description' => 'Consulta reportes y exportaciones.'],
            ['key' => RoleKey::COMPLIANCE, 'name' => 'Cumplimiento', 'description' => 'Consulta evidencia y auditoria.'],
        ])->each(fn (array $role) => Role::query()->updateOrCreate(
            ['key' => $role['key']],
            $role + ['is_system' => true],
        ));
    }
}