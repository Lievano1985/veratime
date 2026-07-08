<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['key' => 'owner', 'name' => 'Propietario', 'description' => 'Control inicial de la empresa.'],
            ['key' => 'admin', 'name' => 'Administrador', 'description' => 'Administra configuracion y usuarios.'],
            ['key' => 'hr', 'name' => 'Recursos Humanos', 'description' => 'Opera trabajadores, jornadas e incidencias.'],
            ['key' => 'supervisor', 'name' => 'Supervisor', 'description' => 'Consulta equipos y revisa alertas.'],
            ['key' => 'payroll', 'name' => 'Nomina', 'description' => 'Consulta reportes y exportaciones.'],
            ['key' => 'compliance', 'name' => 'Cumplimiento', 'description' => 'Consulta evidencia y auditoria.'],
        ])->each(fn (array $role) => Role::query()->updateOrCreate(
            ['key' => $role['key']],
            $role + ['is_system' => true],
        ));
    }
}
