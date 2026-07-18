<?php

namespace Database\Seeders;

use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\CreateOrganizationalUnitAction;
use App\Domains\Scheduling\Actions\AssignScheduleProfileAction;
use App\Domains\Scheduling\Actions\CreateScheduleProfileAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileCycleRulesAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileFlexibleRulesAction;
use App\Domains\Scheduling\Actions\ReplaceScheduleProfileOnCallRulesAction;
use App\Domains\Scheduling\Actions\UpdateScheduleProfileAction;
use App\Domains\Scheduling\Actions\UpdateShiftTemplateAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileAssignment;
use App\Models\ShiftTemplate;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class VeraTimeScheduleProfileScenarioSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'VeraDemo123!';
    private const EFFECTIVE_FROM = '2026-08-01';

    public function run(): void
    {
        $this->officePatternScenario();
        $this->storeCalendarScenario();
        $this->constructionInheritanceScenario();
        $this->noProfileScenario();
        $this->cyclePatternScenario();
        $this->flexibleScenario();
        $this->onCallScenario();
    }

    private function officePatternScenario(): void
    {
        $company = $this->company('VTSP-OFFICE', 'Demo Oficina por Patron');
        $users = $this->users($company, 'office');
        $center = $this->center($company, 'CORP', 'Oficinas Corporativas');
        $admin = $this->unit($company, $center, 'ADM', 'Administracion', 'department');
        $rh = $this->unit($company, $center, 'RH', 'Recursos Humanos', 'area', $admin);
        $template = $this->shiftTemplate($company, 'ADMIN-08-16', 'Administrativo 08:00-16:00', '08:00', '16:00');
        $profile = $this->profile($company, 'OFFICE-WEEKLY', 'Oficina semanal', 'pattern', 'weekly', $this->weeklyRules($template));

        $this->assignProfile($company, $profile, [
            'assignment_scope' => 'company',
            'effective_from' => self::EFFECTIVE_FROM,
            'reason' => 'Escenario demo: herencia desde empresa.',
            'metadata' => ['scenario' => 'office', 'expected_origin' => 'company'],
        ], $users['rh']);

        foreach ([
            ['OFF-001', 'Oficina Demo Ana', 'Analista administrativo'],
            ['OFF-002', 'Oficina Demo Bruno', 'Generalista RH'],
            ['OFF-003', 'Oficina Demo Carla', 'Asistente de direccion'],
        ] as [$code, $name, $position]) {
            $context = $this->worker($company, $center, $code, $name, $position);
            $this->primaryUnit($company, $context['relationship'], $rh);
        }
    }

    private function storeCalendarScenario(): void
    {
        $company = $this->company('VTSP-STORE', 'Demo Tienda por Calendario');
        $users = $this->users($company, 'store');
        $center = $this->center($company, 'PLAZA', 'Sucursal Plaza Central');

        $sales = $this->unit($company, $center, 'VENTA', 'Piso de venta', 'area');
        $warehouse = $this->unit($company, $center, 'ALM', 'Almacen', 'area');
        $admin = $this->unit($company, $center, 'ADM', 'Administracion', 'area');

        $this->shiftTemplate($company, 'OPEN-08-16', 'Apertura 08:00-16:00', '08:00', '16:00');
        $this->shiftTemplate($company, 'MID-11-19', 'Intermedio 11:00-19:00', '11:00', '19:00');
        $this->shiftTemplate($company, 'CLOSE-14-22', 'Cierre 14:00-22:00', '14:00', '22:00');

        $profile = $this->profile($company, 'STORE-CALENDAR', 'Operacion por calendario', 'calendar');
        $this->assignProfile($company, $profile, [
            'assignment_scope' => 'company',
            'effective_from' => self::EFFECTIVE_FROM,
            'reason' => 'Escenario demo: perfil por calendario desde empresa.',
            'metadata' => ['scenario' => 'store', 'expected_origin' => 'company'],
        ], $users['rh']);

        foreach ([
            ['STR-001', 'Tienda Demo Ana', 'Vendedora', $sales],
            ['STR-002', 'Tienda Demo Bruno', 'Cajero', $sales],
            ['STR-003', 'Tienda Demo Carla', 'Almacenista', $warehouse],
            ['STR-004', 'Tienda Demo Diego', 'Administrativo', $admin],
        ] as [$code, $name, $position, $unit]) {
            $context = $this->worker($company, $center, $code, $name, $position);
            $this->primaryUnit($company, $context['relationship'], $unit);
        }
    }

    private function constructionInheritanceScenario(): void
    {
        $company = $this->company('VTSP-CONSTRUCT', 'Demo Constructora con Herencia');
        $users = $this->users($company, 'construction', withSupervisor: true);

        $office = $this->center($company, 'CORP', 'Oficinas Corporativas');
        $site = $this->center($company, 'NORTE', 'Obra Norte', 'America/Monterrey');

        $admin = $this->unit($company, $office, 'ADM', 'Departamento Administracion', 'department');
        $operations = $this->unit($company, $site, 'OPS', 'Departamento Operaciones', 'department');
        $construction = $this->unit($company, $site, 'CONST', 'Area Construccion', 'area', $operations);
        $warehouse = $this->unit($company, $site, 'ALM', 'Area Almacen', 'area', $operations);

        $adminTemplate = $this->shiftTemplate($company, 'ADMIN-08-16', 'Administrativo 08:00-16:00', '08:00', '16:00');
        $warehouseTemplate = $this->shiftTemplate($company, 'WARE-07-15', 'Almacen 07:00-15:00', '07:00', '15:00');

        $companyProfile = $this->profile($company, 'CONST-BASE', 'Base administrativa', 'pattern', 'weekly', $this->weeklyRules($adminTemplate));
        $siteProfile = $this->profile($company, 'CONST-CALENDAR', 'Operacion variable de obra', 'calendar');
        $warehouseProfile = $this->profile($company, 'CONST-WAREHOUSE', 'Almacen semanal', 'pattern', 'weekly', $this->weeklyRules($warehouseTemplate));
        $directProfile = $this->profile($company, 'CONST-DIRECT-CAL', 'Excepcion directa calendario', 'calendar');

        $adminWorker = $this->worker($company, $office, 'CON-001', 'Constructora Demo Administracion', 'Coordinador administrativo');
        $constructionWorker = $this->worker($company, $site, 'CON-002', 'Constructora Demo Construccion', 'Residente de obra');
        $warehouseWorker = $this->worker($company, $site, 'CON-003', 'Constructora Demo Almacen', 'Encargado de almacen');
        $directWorker = $this->worker($company, $site, 'CON-004', 'Constructora Demo Excepcion', 'Especialista de obra');

        $this->primaryUnit($company, $adminWorker['relationship'], $admin);
        $this->primaryUnit($company, $constructionWorker['relationship'], $construction);
        $this->primaryUnit($company, $warehouseWorker['relationship'], $warehouse);
        $this->primaryUnit($company, $directWorker['relationship'], $construction);

        $this->assignProfile($company, $companyProfile, [
            'assignment_scope' => 'company',
            'effective_from' => self::EFFECTIVE_FROM,
            'reason' => 'Escenario demo: base de empresa.',
            'metadata' => ['scenario' => 'construction', 'expected_origin' => 'company'],
        ], $users['rh']);

        $this->assignProfile($company, $siteProfile, [
            'assignment_scope' => 'center',
            'center_id' => $site->id,
            'effective_from' => self::EFFECTIVE_FROM,
            'reason' => 'Escenario demo: excepcion por centro Obra Norte.',
            'metadata' => ['scenario' => 'construction', 'expected_origin' => 'center'],
        ], $users['rh']);

        $this->assignProfile($company, $warehouseProfile, [
            'assignment_scope' => 'organizational_unit',
            'organizational_unit_id' => $warehouse->id,
            'effective_from' => self::EFFECTIVE_FROM,
            'reason' => 'Escenario demo: excepcion por unidad Almacen.',
            'metadata' => ['scenario' => 'construction', 'expected_origin' => 'organizational_unit'],
        ], $users['rh']);

        $this->assignProfile($company, $directProfile, [
            'assignment_scope' => 'employment_relationship',
            'employment_relationship_id' => $directWorker['relationship']->id,
            'effective_from' => self::EFFECTIVE_FROM,
            'reason' => 'Escenario demo: excepcion directa por relacion laboral.',
            'metadata' => ['scenario' => 'construction', 'expected_origin' => 'employment_relationship'],
        ], $users['rh']);

        $this->supervisorScope($company, $users['supervisor'], $construction);
    }

    private function noProfileScenario(): void
    {
        $company = $this->company('VTSP-NOPROFILE', 'Demo Sin Perfil de Horario');
        $this->users($company, 'noprofile');
        $center = $this->center($company, 'BASE', 'Centro sin perfil');
        $area = $this->unit($company, $center, 'AREA', 'Area sin perfil', 'area');

        foreach ([
            ['NOP-001', 'Sin Perfil Demo Ana', 'Operador'],
            ['NOP-002', 'Sin Perfil Demo Bruno', 'Auxiliar'],
        ] as [$code, $name, $position]) {
            $context = $this->worker($company, $center, $code, $name, $position);
            $this->primaryUnit($company, $context['relationship'], $area);
        }
    }

    private function cyclePatternScenario(): void
    {
        $company = $this->company('VTSP-CYCLE', 'Demo Ciclo Rotativo');
        $users = $this->users($company, 'cycle');
        $center = $this->center($company, 'ROT', 'Centro Rotativo');
        $unit = $this->unit($company, $center, 'OPS', 'Operacion Rotativa', 'area');

        $morning = $this->shiftTemplate($company, 'CYCLE-MORNING', 'Manana 06:00-14:00', '06:00', '14:00');
        $afternoon = $this->shiftTemplate($company, 'CYCLE-AFTERNOON', 'Tarde 14:00-22:00', '14:00', '22:00');
        $night = $this->shiftTemplate($company, 'CYCLE-NIGHT', 'Noche 22:00-06:00', '22:00', '06:00', 0, 1);
        $profile = $this->profile($company, 'CYCLE-2X2', 'Ciclo rotativo 2x2', 'pattern', 'cycle');

        app(ReplaceScheduleProfileCycleRulesAction::class)->handle($company, $profile, [
            ['cycle_day' => 1, 'day_type' => 'shift', 'shift_template_id' => $morning->id],
            ['cycle_day' => 2, 'day_type' => 'shift', 'shift_template_id' => $morning->id],
            ['cycle_day' => 3, 'day_type' => 'shift', 'shift_template_id' => $afternoon->id],
            ['cycle_day' => 4, 'day_type' => 'shift', 'shift_template_id' => $afternoon->id],
            ['cycle_day' => 5, 'day_type' => 'shift', 'shift_template_id' => $night->id],
            ['cycle_day' => 6, 'day_type' => 'shift', 'shift_template_id' => $night->id],
            ['cycle_day' => 7, 'day_type' => 'rest'],
            ['cycle_day' => 8, 'day_type' => 'rest'],
        ]);

        $context = $this->worker($company, $center, 'CYC-001', 'Ciclo Demo Ana', 'Operadora rotativa');
        $this->primaryUnit($company, $context['relationship'], $unit);
        $this->assignProfile($company, $profile, [
            'assignment_scope' => 'company',
            'effective_from' => self::EFFECTIVE_FROM,
            'reason' => 'Escenario demo: ciclo rotativo desde empresa.',
            'metadata' => ['scenario' => 'cycle', 'expected_origin' => 'company'],
        ], $users['rh']);
    }

    private function flexibleScenario(): void
    {
        $company = $this->company('VTSP-FLEX', 'Demo Horario Flexible');
        $users = $this->users($company, 'flex');
        $center = $this->center($company, 'FLEX', 'Centro Flexible');
        $unit = $this->unit($company, $center, 'FLEXOPS', 'Operacion Flexible', 'area');
        $profile = $this->profile($company, 'FLEX-480', 'Flexible 480 minutos', 'flexible');

        app(ReplaceScheduleProfileFlexibleRulesAction::class)->handle($company, $profile, $this->flexibleRules());

        $context = $this->worker($company, $center, 'FLX-001', 'Flexible Demo Ana', 'Consultora flexible');
        $this->primaryUnit($company, $context['relationship'], $unit);
        $this->assignProfile($company, $profile, [
            'assignment_scope' => 'company',
            'effective_from' => self::EFFECTIVE_FROM,
            'reason' => 'Escenario demo: horario flexible desde empresa.',
            'metadata' => ['scenario' => 'flexible', 'expected_origin' => 'company'],
        ], $users['rh']);
    }

    private function onCallScenario(): void
    {
        $company = $this->company('VTSP-ONCALL', 'Demo Bajo Demanda');
        $users = $this->users($company, 'oncall');
        $center = $this->center($company, 'MANT', 'Centro de Mantenimiento');
        $unit = $this->unit($company, $center, 'MANT', 'Mantenimiento', 'area');
        $profile = $this->profile($company, 'ONCALL-BASE', 'Bajo demanda base', 'on_call');

        app(ReplaceScheduleProfileOnCallRulesAction::class)->handle($company, $profile, $this->onCallRules());

        $context = $this->worker($company, $center, 'ONC-001', 'Bajo Demanda Demo Ana', 'Tecnica de mantenimiento');
        $this->primaryUnit($company, $context['relationship'], $unit);
        $this->assignProfile($company, $profile, [
            'assignment_scope' => 'company',
            'effective_from' => self::EFFECTIVE_FROM,
            'reason' => 'Escenario demo: bajo demanda desde empresa.',
            'metadata' => ['scenario' => 'on_call', 'expected_origin' => 'company'],
        ], $users['rh']);
    }

    private function company(string $taxId, string $name): Company
    {
        $company = Company::query()->updateOrCreate(
            ['tax_id' => $taxId],
            [
                'name' => $name,
                'legal_name' => $name.' SA de CV',
                'timezone' => 'America/Mexico_City',
                'status' => 'active',
                'settings' => ['demo' => true, 'scenario' => 'schedule_profiles'],
            ],
        );

        CompanySetting::query()->updateOrCreate(
            ['company_id' => $company->id],
            Company::defaultSettings() + [
                'company_id' => $company->id,
                'metadata' => ['demo' => true, 'scenario' => 'schedule_profiles'],
            ],
        );

        return $company->refresh();
    }

    /**
     * @return array{owner: User, admin: User, rh: User, supervisor?: User}
     */
    private function users(Company $company, string $slug, bool $withSupervisor = false): array
    {
        $users = [
            'owner' => $this->user($company, RoleKey::OWNER, 'Owner '.ucfirst($slug).' Demo', "owner.{$slug}.demo@veratime.local", true),
            'admin' => $this->user($company, RoleKey::ADMIN, 'Admin '.ucfirst($slug).' Demo', "admin.{$slug}.demo@veratime.local"),
            'rh' => $this->user($company, RoleKey::RH, 'RH '.ucfirst($slug).' Demo', "rh.{$slug}.demo@veratime.local"),
        ];

        if ($withSupervisor) {
            $users['supervisor'] = $this->user($company, RoleKey::SUPERVISOR, 'Supervisor Construction Demo', 'supervisor.construction.demo@veratime.local');
        }

        return $users;
    }

    private function user(Company $company, string $roleKey, string $name, string $email, bool $default = false): User
    {
        $role = Role::query()->firstOrCreate(
            ['key' => $roleKey],
            ['name' => strtoupper($roleKey), 'description' => 'Rol demo local Vera Time.', 'is_system' => true],
        );

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make(self::DEMO_PASSWORD), 'status' => 'active'],
        );

        $user->companies()->syncWithoutDetaching([
            $company->id => [
                'role_id' => $role->id,
                'status' => 'active',
                'is_default' => $default,
            ],
        ]);

        return $user->refresh();
    }

    private function center(Company $company, string $code, string $name, string $timezone = 'America/Mexico_City'): Center
    {
        return Center::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => $code],
            [
                'name' => $name,
                'timezone' => $timezone,
                'status' => 'active',
                'address' => [
                    'demo' => true,
                    'country_code' => 'MX',
                    'jurisdiction_code' => $timezone === 'America/Monterrey' ? 'MX-NLE' : 'MX-CMX',
                    'city' => $timezone === 'America/Monterrey' ? 'Monterrey' : 'Ciudad de Mexico',
                ],
                'metadata' => ['demo' => true, 'scenario' => 'schedule_profiles'],
            ],
        );
    }

    private function unit(Company $company, Center $center, string $code, string $name, string $type, ?OrganizationalUnit $parent = null): OrganizationalUnit
    {
        $unit = OrganizationalUnit::query()
            ->where('company_id', $company->id)
            ->where('center_id', $center->id)
            ->where('code', $code)
            ->first();

        if ($unit) {
            return $unit->refresh();
        }

        return app(CreateOrganizationalUnitAction::class)->handle($company, $center, [
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'metadata' => ['demo' => true, 'scenario' => 'schedule_profiles'],
        ], $parent);
    }

    private function shiftTemplate(Company $company, string $code, string $name, string $start, string $end, int $startOffset = 0, int $endOffset = 0): ShiftTemplate
    {
        $data = [
            'code' => $code,
            'name' => $name,
            'description' => 'Plantilla demo para escenarios manuales de perfiles.',
            'status' => 'active',
            'metadata' => ['demo' => true, 'scenario' => 'schedule_profiles'],
        ];
        $segments = [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => $start, 'end_local_time' => $end, 'start_day_offset' => $startOffset, 'end_day_offset' => $endOffset, 'sort_order' => 1],
        ];
        $template = ShiftTemplate::query()->where('company_id', $company->id)->where('code', $code)->first();

        return $template
            ? app(UpdateShiftTemplateAction::class)->handle($company, $template, $data, $segments)
            : app(CreateShiftTemplateAction::class)->handle($company, $data, $segments);
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     */
    private function profile(Company $company, string $code, string $name, string $type, ?string $patternMode = null, array $rules = []): ScheduleProfile
    {
        $data = [
            'code' => $code,
            'name' => $name,
            'description' => 'Perfil demo para escenarios manuales.',
            'profile_type' => $type,
            'pattern_mode' => $patternMode,
            'status' => 'active',
            'metadata' => ['demo' => true, 'scenario' => 'schedule_profiles'],
        ];
        $profile = ScheduleProfile::query()->where('company_id', $company->id)->where('code', $code)->first();

        return $profile
            ? app(UpdateScheduleProfileAction::class)->handle($company, $profile, $data, $type === 'pattern' && $patternMode === 'weekly' ? $rules : null)
            : app(CreateScheduleProfileAction::class)->handle($company, $data, $rules);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function weeklyRules(ShiftTemplate $template): array
    {
        $rules = [];

        for ($day = 1; $day <= 5; $day++) {
            $rules[] = ['day_of_week' => $day, 'day_type' => 'shift', 'shift_template_id' => $template->id];
        }

        $rules[] = ['day_of_week' => 6, 'day_type' => 'rest'];
        $rules[] = ['day_of_week' => 7, 'day_type' => 'rest'];

        return $rules;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function flexibleRules(): array
    {
        $rules = [];
        for ($day = 1; $day <= 5; $day++) {
            $rules[] = [
                'day_of_week' => $day,
                'day_type' => 'work',
                'required_minutes' => 480,
                'window_start_local_time' => '07:00',
                'window_end_local_time' => '20:00',
            ];
        }
        $rules[] = ['day_of_week' => 6, 'day_type' => 'rest'];
        $rules[] = ['day_of_week' => 7, 'day_type' => 'rest'];

        return $rules;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function onCallRules(): array
    {
        $rules = [];
        for ($day = 1; $day <= 7; $day++) {
            $rules[] = [
                'day_of_week' => $day,
                'day_type' => 'on_call',
                'availability_start_local_time' => '06:00',
                'availability_end_local_time' => '22:00',
                'max_work_minutes' => 480,
            ];
        }

        return $rules;
    }

    /**
     * @return array{worker: Worker, relationship: EmploymentRelationship}
     */
    private function worker(Company $company, Center $center, string $code, string $name, string $position): array
    {
        $worker = Worker::query()->updateOrCreate(
            ['company_id' => $company->id, 'employee_code' => $code],
            [
                'full_name' => $name,
                'email' => strtolower($code).'@scenario.veratime.local',
                'phone' => null,
                'curp' => null,
                'rfc' => null,
                'status' => 'active',
                'source' => 'demo_seed',
                'external_id' => 'scenario-'.$code,
                'metadata' => ['demo' => true, 'scenario' => 'schedule_profiles'],
            ],
        );

        $relationship = EmploymentRelationship::query()->updateOrCreate(
            ['company_id' => $company->id, 'worker_id' => $worker->id, 'external_id' => 'scenario-rel-'.$code],
            [
                'center_id' => $center->id,
                'position_name' => $position,
                'started_at' => self::EFFECTIVE_FROM,
                'ended_at' => null,
                'status' => 'active',
                'source' => 'demo_seed',
                'metadata' => ['demo' => true, 'scenario' => 'schedule_profiles'],
            ],
        );

        return ['worker' => $worker->refresh(), 'relationship' => $relationship->refresh()];
    }

    private function primaryUnit(Company $company, EmploymentRelationship $relationship, OrganizationalUnit $unit): void
    {
        if ($relationship->employmentUnitAssignments()->where('assignment_type', 'primary')->where('status', 'active')->exists()) {
            return;
        }

        app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $unit, [
            'effective_from' => self::EFFECTIVE_FROM,
            'source' => 'system',
            'reason' => 'Escenario demo de perfiles de horario.',
            'metadata' => ['demo' => true, 'scenario' => 'schedule_profiles'],
        ]);
    }

    private function assignProfile(Company $company, ScheduleProfile $profile, array $data, User $createdBy): void
    {
        $exists = ScheduleProfileAssignment::query()
            ->where('company_id', $company->id)
            ->where('assignment_scope', $data['assignment_scope'])
            ->whereDate('effective_from', $data['effective_from'])
            ->where('status', 'active')
            ->when($data['assignment_scope'] === 'company', fn ($query) => $query->whereNull('center_id')->whereNull('organizational_unit_id')->whereNull('employment_relationship_id'))
            ->when($data['assignment_scope'] === 'center', fn ($query) => $query->where('center_id', $data['center_id']))
            ->when($data['assignment_scope'] === 'organizational_unit', fn ($query) => $query->where('organizational_unit_id', $data['organizational_unit_id']))
            ->when($data['assignment_scope'] === 'employment_relationship', fn ($query) => $query->where('employment_relationship_id', $data['employment_relationship_id']))
            ->exists();

        if ($exists) {
            return;
        }

        app(AssignScheduleProfileAction::class)->handle($company, $profile, ['source' => 'system'] + $data, $createdBy);
    }

    private function supervisorScope(Company $company, User $supervisor, OrganizationalUnit $unit): void
    {
        if ($supervisor->operationalScopeAssignments()
            ->where('company_id', $company->id)
            ->where('organizational_unit_id', $unit->id)
            ->where('status', 'active')
            ->exists()) {
            return;
        }

        app(AssignOperationalScopeAction::class)->handle($company, $supervisor, [
            'effective_from' => self::EFFECTIVE_FROM,
            'responsibility_type' => 'supervisor',
            'source' => 'system',
            'reason' => 'Escenario demo: supervisor limitado a Area Construccion.',
            'metadata' => ['demo' => true, 'scenario' => 'schedule_profiles'],
        ], unit: $unit);
    }
}
