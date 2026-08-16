<?php

namespace Database\Seeders;

use App\Domains\MandatoryRestDays\Actions\CreateMandatoryRestDayAction;
use App\Domains\Organization\Actions\AssignOperationalScopeAction;
use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\CreateOrganizationalUnitAction;
use App\Domains\Schedules\Actions\CreateScheduleAssignmentAction;
use App\Domains\Schedules\Actions\SaveScheduleDaysAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\AssignScheduleProfileAction;
use App\Domains\Scheduling\Actions\CreateScheduleProfileAction;
use App\Domains\Scheduling\Actions\UpdateShiftTemplateAction;
use App\Domains\Scheduling\Actions\UpdateScheduleProfileAction;
use App\Domains\TimeRecords\Actions\CreateTimeEventAction;
use App\Domains\Workers\Actions\CreateOrUpdateWorkerCredentialAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\EmploymentRelationship;
use App\Models\LaborCondition;
use App\Models\MandatoryRestDay;
use App\Models\OrganizationalUnit;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleBreak;
use App\Models\ScheduleDay;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileAssignment;
use App\Models\ShiftTemplate;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class VeraTimeDemoSeeder extends Seeder
{
    private const COMPANY_TAX_ID = 'VTD260712XX1';
    private const DEMO_PASSWORD = 'VeraDemo123!';
    private const DEMO_PIN = '1234';
    private const DEMO_ATTENDANCE_START = '2026-07-20';
    private const DEMO_ATTENDANCE_END = '2026-08-05';

    public function run(): void
    {
        $company = $this->company();
        $users = $this->users($company);
        $centers = $this->centers($company);
        $schedules = $this->schedules($company);
        $this->shiftTemplates($company);
        $workers = $this->workers($company, $centers, $schedules);
        $units = $this->organizationalUnits($company, $centers);
        $this->organizationalAssignments($company, $users, $workers, $units);
        $this->scheduleProfiles($company, $users['rh_admin'], $centers, $workers, $units);

        $this->mandatoryRestDays($company);
        $this->timeEvents($company, $users['rh_admin'], $workers);
    }

    private function company(): Company
    {
        $company = Company::query()->updateOrCreate(
            ['tax_id' => self::COMPANY_TAX_ID],
            [
                'name' => 'Vera Time Demo Completo',
                'legal_name' => 'Vera Time Demo Completo SA de CV',
                'timezone' => 'America/Mexico_City',
                'status' => 'active',
                'settings' => ['demo' => true, 'local_only' => true],
            ],
        );

        CompanySetting::query()->updateOrCreate(
            ['company_id' => $company->id],
            Company::defaultSettings() + [
                'company_id' => $company->id,
                'metadata' => ['demo' => true],
            ],
        );

        return $company->refresh();
    }

    /**
     * @return array{admin: User, rh_admin: User, rh_operativo: User, supervisor: User}
     */
    private function users(Company $company): array
    {
        $roles = [
            'admin' => $this->role(RoleKey::ADMIN_EMPRESA, 'Administrador de empresa'),
            'rh_admin' => $this->role(RoleKey::RH_ADMIN, 'RH administrador'),
            'rh_operativo' => $this->role(RoleKey::RH_OPERATIVO, 'RH operativo'),
            'supervisor' => $this->role(RoleKey::SUPERVISOR, 'Supervisor'),
        ];

        return [
            'admin' => $this->user($company, $roles['admin'], 'Demo Admin', 'admin.demo@veratime.local', true),
            'rh_admin' => $this->user($company, $roles['rh_admin'], 'Demo RH', 'rh.demo@veratime.local'),
            'rh_operativo' => $this->user($company, $roles['rh_operativo'], 'Demo RH Operativo', 'rh.operativo.demo@veratime.local'),
            'supervisor' => $this->user($company, $roles['supervisor'], 'Demo Supervisor', 'supervisor.demo@veratime.local'),
        ];
    }

    private function role(string $key, string $name): Role
    {
        return Role::query()->updateOrCreate(
            ['key' => $key],
            ['name' => $name, 'description' => 'Rol demo local Vera Time.', 'is_system' => true],
        );
    }

    private function user(Company $company, Role $role, string $name, string $email, bool $default = false): User
    {
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

        if ($default) {
            $user->companies()
                ->wherePivot('is_default', true)
                ->where('companies.id', '!=', $company->id)
                ->updateExistingPivot($company->id, ['is_default' => true]);
        }

        return $user->refresh();
    }

    /**
     * @return array{matriz: Center, planta: Center}
     */
    private function centers(Company $company): array
    {
        return [
            'matriz' => $this->center($company, 'MTZ', 'Matriz Demo', 'America/Mexico_City'),
            'planta' => $this->center($company, 'PLT', 'Planta Demo Norte', 'America/Monterrey'),
        ];
    }

    private function center(Company $company, string $code, string $name, string $timezone): Center
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
                    'city' => str_contains($timezone, 'Monterrey') ? 'Monterrey' : 'Ciudad de Mexico',
                    'jurisdiction_code' => str_contains($timezone, 'Monterrey') ? 'MX-NLE' : 'MX-CMX',
                ],
                'metadata' => ['demo' => true],
            ],
        );
    }

    /**
     * @return array{diurno: Schedule, nocturno: Schedule}
     */
    private function schedules(Company $company): array
    {
        $diurno = Schedule::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'DEM-DIURNO'],
            [
                'name' => 'Demo Diurno 08-17',
                'legal_type' => 'diurna',
                'timezone' => 'America/Mexico_City',
                'status' => 'active',
                'effective_from' => '2026-08-01',
                'effective_to' => null,
                'metadata' => ['demo' => true],
            ],
        );

        $nocturno = Schedule::query()->updateOrCreate(
            ['company_id' => $company->id, 'code' => 'DEM-NOCTURNO'],
            [
                'name' => 'Demo Nocturno 22-06',
                'legal_type' => 'nocturna',
                'timezone' => 'America/Monterrey',
                'status' => 'active',
                'effective_from' => '2026-08-01',
                'effective_to' => null,
                'metadata' => ['demo' => true, 'crosses_midnight' => true],
            ],
        );

        $this->scheduleDays($company, $diurno, '08:00', '17:00', false);
        $this->scheduleDays($company, $nocturno, '22:00', '06:00', true);

        return ['diurno' => $diurno->refresh(), 'nocturno' => $nocturno->refresh()];
    }

    private function scheduleDays(Company $company, Schedule $schedule, string $start, string $end, bool $crossesMidnight): void
    {
        $days = [];

        for ($day = 1; $day <= 5; $day++) {
            $days[] = [
                'day_of_week' => $day,
                'is_working_day' => true,
                'start_time' => $start,
                'end_time' => $end,
                'crosses_midnight' => $crossesMidnight,
            ];
        }

        $days[] = ['day_of_week' => 0, 'is_working_day' => false];
        $days[] = ['day_of_week' => 6, 'is_working_day' => false];

        app(SaveScheduleDaysAction::class)->handle($company, $schedule, $days);

        $monday = $schedule->days()->where('day_of_week', 1)->first();
        if ($monday) {
            $this->scheduleBreak($company, $monday, 'Comida demo', $crossesMidnight ? null : '13:00', $crossesMidnight ? null : '14:00', 60);
        }
    }

    private function scheduleBreak(Company $company, ScheduleDay $day, string $name, ?string $start, ?string $end, int $duration): void
    {
        $break = ScheduleBreak::query()
            ->where('company_id', $company->id)
            ->where('schedule_day_id', $day->id)
            ->where('name', $name)
            ->first() ?? new ScheduleBreak();

        $break->fill([
            'name' => $name,
            'start_time' => $start,
            'end_time' => $end,
            'duration_minutes' => $duration,
            'is_paid' => false,
            'is_required' => true,
        ]);
        $break->company()->associate($company);
        $break->scheduleDay()->associate($day);
        $break->save();
    }

    private function shiftTemplates(Company $company): void
    {
        $this->shiftTemplate($company, 'APER', 'Apertura demo', [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '16:00', 'sort_order' => 1],
        ]);

        $this->shiftTemplate($company, 'INT', 'Intermedio demo', [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '11:00', 'end_local_time' => '19:00', 'sort_order' => 1],
        ]);

        $this->shiftTemplate($company, 'CIERRE', 'Cierre demo', [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '14:00', 'end_local_time' => '22:00', 'sort_order' => 1],
        ]);

        $this->shiftTemplate($company, 'NOCT', 'Nocturno demo', [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '22:00', 'end_local_time' => '06:00', 'start_day_offset' => 0, 'end_day_offset' => 1, 'sort_order' => 1],
        ]);

        $this->shiftTemplate($company, 'PART', 'Jornada partida demo', [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '08:00', 'end_local_time' => '13:00', 'sort_order' => 1],
            ['segment_type' => 'break', 'timing_mode' => 'fixed', 'start_local_time' => '13:00', 'end_local_time' => '15:00', 'is_paid' => false, 'is_required' => true, 'sort_order' => 2],
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '15:00', 'end_local_time' => '18:00', 'sort_order' => 3],
            ['segment_type' => 'break', 'timing_mode' => 'duration', 'duration_minutes' => 30, 'is_paid' => false, 'is_required' => true, 'sort_order' => 4],
        ]);
    }

    private function shiftTemplate(Company $company, string $code, string $name, array $segments): ShiftTemplate
    {
        $data = [
            'code' => $code,
            'name' => $name,
            'description' => 'Plantilla demo local sin efecto operativo.',
            'status' => 'active',
            'metadata' => ['demo' => true],
        ];

        $template = ShiftTemplate::query()
            ->where('company_id', $company->id)
            ->where('code', $code)
            ->first();

        return $template
            ? app(UpdateShiftTemplateAction::class)->handle($company, $template, $data, $segments)
            : app(CreateShiftTemplateAction::class)->handle($company, $data, $segments);
    }

    /**
     * @return array<string, array{worker: Worker, relationship: EmploymentRelationship, center: Center}>
     */
    private function workers(Company $company, array $centers, array $schedules): array
    {
        $data = [
            'ana' => ['VT-001', 'Ana Demo Lopez', 'ana.demo@veratime.local', $centers['matriz'], $schedules['diurno'], 'Analista RH'],
            'bruno' => ['VT-002', 'Bruno Demo Perez', 'bruno.demo@veratime.local', $centers['planta'], $schedules['nocturno'], 'Operador nocturno'],
            'carla' => ['VT-003', 'Carla Demo Ruiz', 'carla.demo@veratime.local', $centers['matriz'], $schedules['diurno'], 'Supervisora demo'],
            'diego' => ['VT-004', 'Diego Demo Santos', 'diego.demo@veratime.local', $centers['planta'], $schedules['diurno'], 'Auxiliar almacen'],
        ];

        $workers = [];

        foreach ($data as $key => [$code, $name, $email, $center, $schedule, $position]) {
            $worker = Worker::query()->updateOrCreate(
                ['company_id' => $company->id, 'employee_code' => $code],
                [
                    'full_name' => $name,
                    'email' => $email,
                    'phone' => '555000'.substr($code, -3),
                    'curp' => null,
                    'rfc' => null,
                    'status' => 'active',
                    'source' => 'demo_seed',
                    'external_id' => 'demo-'.$code,
                    'metadata' => ['demo' => true],
                ],
            );

            $relationship = EmploymentRelationship::query()->updateOrCreate(
                ['company_id' => $company->id, 'worker_id' => $worker->id, 'external_id' => 'demo-rel-'.$code],
                [
                    'center_id' => $center->id,
                    'position_name' => $position,
                    'started_at' => self::DEMO_ATTENDANCE_START,
                    'ended_at' => null,
                    'status' => 'active',
                    'source' => 'demo_seed',
                    'metadata' => ['demo' => true],
                ],
            );

            $this->laborCondition($company, $relationship, $schedule);

            app(CreateOrUpdateWorkerCredentialAction::class)->handle($company, $worker, [
                'access_code' => 'K-'.$code,
                'temporal_pin' => self::DEMO_PIN,
                'status' => 'active',
            ]);

            if (! $worker->scheduleAssignments()->where('company_id', $company->id)->where('status', 'active')->exists()) {
                app(CreateScheduleAssignmentAction::class)->handle($company, $worker, $schedule, $relationship, [
                    'effective_from' => '2026-08-01',
                    'source' => 'demo_seed',
                    'metadata' => ['demo' => true],
                ]);
            }

            $workers[$key] = ['worker' => $worker->refresh(), 'relationship' => $relationship->refresh(), 'center' => $center];
        }

        return $workers;
    }

    private function laborCondition(Company $company, EmploymentRelationship $relationship, Schedule $schedule): void
    {
        $condition = LaborCondition::query()
            ->where('company_id', $company->id)
            ->where('employment_relationship_id', $relationship->id)
            ->whereDate('effective_from', self::DEMO_ATTENDANCE_START)
            ->first() ?? new LaborCondition();

        $condition->fill([
            'employment_relationship_id' => $relationship->id,
            'schedule_id' => $schedule->id,
            'work_modality' => 'onsite',
            'weekly_hours' => 48,
            'rest_day_of_week' => 0,
            'policy_id' => null,
            'effective_from' => self::DEMO_ATTENDANCE_START,
            'effective_to' => null,
            'status' => 'active',
            'metadata' => ['demo' => true],
        ]);
        $condition->company()->associate($company);
        $condition->save();
    }
    /**
     * @return array<string, OrganizationalUnit>
     */
    private function organizationalUnits(Company $company, array $centers): array
    {
        $create = app(CreateOrganizationalUnitAction::class);

        $admin = $this->unit($company, $centers['matriz'], 'ADM', 'Administracion', 'department');
        $rh = $this->unit($company, $centers['matriz'], 'RH', 'Recursos Humanos', 'area', $admin);
        $accounting = $this->unit($company, $centers['matriz'], 'CONT', 'Contabilidad', 'area', $admin);

        $operations = $this->unit($company, $centers['planta'], 'OPS', 'Operaciones', 'department');
        $production = $this->unit($company, $centers['planta'], 'PROD', 'Produccion', 'area', $operations);
        $shiftA = $this->unit($company, $centers['planta'], 'PROD-A', 'Equipo Turno A', 'team', $production);
        $warehouse = $this->unit($company, $centers['planta'], 'ALM', 'Almacen', 'area', $operations);

        return [
            'admin' => $admin,
            'rh' => $rh,
            'accounting' => $accounting,
            'operations' => $operations,
            'production' => $production,
            'shift_a' => $shiftA,
            'warehouse' => $warehouse,
        ];
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
            'metadata' => ['demo' => true],
        ], $parent);
    }

    /**
     * @param array<string, array{worker: Worker, relationship: EmploymentRelationship, center: Center}> $workers
     * @param array<string, OrganizationalUnit> $units
     */
    private function organizationalAssignments(Company $company, array $users, array $workers, array $units): void
    {
        $this->primaryUnit($company, $workers['ana']['relationship'], $units['rh']);
        $this->primaryUnit($company, $workers['bruno']['relationship'], $units['shift_a']);
        $this->primaryUnit($company, $workers['carla']['relationship'], $units['accounting']);
        $this->primaryUnit($company, $workers['diego']['relationship'], $units['warehouse']);

        $this->supervisorScope($company, $users['supervisor'], $units['production']);
    }

    /**
     * @param array<string, Center> $centers
     * @param array<string, array{worker: Worker, relationship: EmploymentRelationship, center: Center}> $workers
     * @param array<string, OrganizationalUnit> $units
     */
    private function scheduleProfiles(Company $company, User $createdBy, array $centers, array $workers, array $units): void
    {
        $apertura = ShiftTemplate::query()
            ->where('company_id', $company->id)
            ->where('code', 'APER')
            ->firstOrFail();

        $pattern = $this->scheduleProfile($company, 'OPAT', 'Oficina por patron demo', 'pattern', 'weekly', $this->weeklyPatternRules($apertura));
        $calendar = $this->scheduleProfile($company, 'OCAL', 'Operacion por calendario demo', 'calendar');

        $this->scheduleProfileAssignment($company, $pattern, [
            'assignment_scope' => 'company',
            'effective_from' => '2026-08-01',
            'reason' => 'Perfil base demo de empresa.',
            'metadata' => ['demo' => true, 'expected_resolution' => 'company'],
        ], $createdBy);

        $this->scheduleProfileAssignment($company, $calendar, [
            'assignment_scope' => 'center',
            'center_id' => $centers['planta']->id,
            'effective_from' => '2026-08-01',
            'reason' => 'Excepcion demo por centro.',
            'metadata' => ['demo' => true, 'expected_resolution' => 'center'],
        ], $createdBy);

        $this->scheduleProfileAssignment($company, $calendar, [
            'assignment_scope' => 'organizational_unit',
            'organizational_unit_id' => $units['rh']->id,
            'effective_from' => '2026-08-01',
            'reason' => 'Excepcion demo por area.',
            'metadata' => ['demo' => true, 'expected_resolution' => 'organizational_unit'],
        ], $createdBy);

        $this->scheduleProfileAssignment($company, $pattern, [
            'assignment_scope' => 'employment_relationship',
            'employment_relationship_id' => $workers['bruno']['relationship']->id,
            'effective_from' => '2026-08-01',
            'reason' => 'Excepcion directa demo por relacion laboral.',
            'metadata' => ['demo' => true, 'expected_resolution' => 'employment_relationship'],
        ], $createdBy);
    }

    private function scheduleProfile(Company $company, string $code, string $name, string $type, ?string $patternMode = null, array $rules = []): ScheduleProfile
    {
        $data = [
            'code' => $code,
            'name' => $name,
            'description' => 'Perfil demo local sin generar programacion diaria.',
            'profile_type' => $type,
            'pattern_mode' => $patternMode,
            'status' => 'active',
            'metadata' => ['demo' => true],
        ];

        $profile = ScheduleProfile::query()
            ->where('company_id', $company->id)
            ->where('code', $code)
            ->first();

        return $profile
            ? app(UpdateScheduleProfileAction::class)->handle($company, $profile, $data, $type === 'pattern' && $patternMode === 'weekly' ? $rules : null)
            : app(CreateScheduleProfileAction::class)->handle($company, $data, $rules);
    }

    private function weeklyPatternRules(ShiftTemplate $template): array
    {
        $rules = [];

        for ($day = 1; $day <= 5; $day++) {
            $rules[] = ['day_of_week' => $day, 'day_type' => 'shift', 'shift_template_id' => $template->id];
        }

        $rules[] = ['day_of_week' => 6, 'day_type' => 'rest'];
        $rules[] = ['day_of_week' => 7, 'day_type' => 'rest'];

        return $rules;
    }

    private function scheduleProfileAssignment(Company $company, ScheduleProfile $profile, array $data, User $createdBy): void
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

    private function primaryUnit(Company $company, EmploymentRelationship $relationship, OrganizationalUnit $unit): void
    {
        if ($relationship->employmentUnitAssignments()->where('assignment_type', 'primary')->where('status', 'active')->exists()) {
            return;
        }

        app(AssignPrimaryOrganizationalUnitAction::class)->handle($company, $relationship, $unit, [
            'effective_from' => '2026-08-01',
            'source' => 'system',
            'reason' => 'Asignacion demo local.',
            'metadata' => ['demo' => true],
        ]);
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
            'effective_from' => '2026-08-01',
            'responsibility_type' => 'supervisor',
            'source' => 'system',
            'reason' => 'Alcance demo limitado a produccion.',
            'metadata' => ['demo' => true],
        ], unit: $unit);
    }
    private function mandatoryRestDays(Company $company): void
    {
        $this->mandatoryRestDay($company, 'Demo descanso empresa', '2026-09-16', 'company_internal', 'company', null, 'Referencia demo interna');
        $this->mandatoryRestDay(null, 'Demo descanso estatal Nuevo Leon', '2026-11-16', 'electoral', 'subnational', 'MX-NLE', 'Referencia demo electoral');
    }

    private function mandatoryRestDay(?Company $company, string $name, string $date, string $type, string $scope, ?string $jurisdictionCode = null, ?string $sourceReference = null): void
    {
        $exists = MandatoryRestDay::query()
            ->where('type', $type)
            ->where('scope', $scope)
            ->whereDate('date', $date)
            ->where('name', $name)
            ->where('country_code', 'MX')
            ->when($company, fn ($query) => $query->where('company_id', $company->id), fn ($query) => $query->whereNull('company_id'))
            ->when($jurisdictionCode, fn ($query) => $query->where('jurisdiction_code', $jurisdictionCode), fn ($query) => $query->whereNull('jurisdiction_code'))
            ->exists();

        if ($exists) {
            return;
        }

        app(CreateMandatoryRestDayAction::class)->handle($company, [
            'name' => $name,
            'date' => $date,
            'type' => $type,
            'scope' => $scope,
            'country_code' => 'MX',
            'jurisdiction_code' => $jurisdictionCode,
            'source_reference' => $sourceReference,
            'capture_source' => 'seeder',
            'metadata' => ['demo' => true],
        ]);
    }

    /**
     * @param array<string, array{worker: Worker, relationship: EmploymentRelationship, center: Center}> $workers
     */
    private function timeEvents(Company $company, User $sourceUser, array $workers): void
    {
        $action = app(CreateTimeEventAction::class);

        $this->deleteObsoleteDemoTimeEvents($company);

        $this->seedDayShiftEvents($action, $company, $workers['ana'], 'ana', 'web', [
            ['date' => '2026-07-20', 'in' => '08:00:00', 'break_start' => '13:00:00', 'break_end' => '14:00:00', 'out' => '17:00:00'],
            ['date' => '2026-07-21', 'in' => '08:04:00', 'break_start' => '13:00:00', 'break_end' => '14:00:00', 'out' => '17:02:00'],
            ['date' => '2026-07-22', 'in' => '08:00:00', 'break_start' => '13:05:00', 'break_end' => '14:00:00', 'out' => '17:00:00'],
            ['date' => '2026-07-23', 'in' => '08:06:00', 'break_start' => '13:00:00', 'break_end' => '14:00:00', 'out' => '17:10:00'],
            ['date' => '2026-07-24', 'in' => '08:00:00', 'break_start' => '13:00:00', 'break_end' => '14:00:00', 'out' => '17:00:00'],
            ['date' => '2026-07-27', 'in' => '08:00:00', 'break_start' => '13:00:00', 'break_end' => '14:00:00', 'out' => '17:00:00'],
            ['date' => '2026-07-28', 'in' => '08:03:00', 'break_start' => '13:00:00', 'break_end' => '14:00:00', 'out' => '17:01:00'],
            ['date' => '2026-07-29', 'in' => '08:00:00', 'break_start' => '13:00:00', 'break_end' => '14:00:00', 'out' => '17:00:00'],
            ['date' => '2026-07-30', 'in' => '08:00:00', 'break_start' => '13:00:00', 'break_end' => '14:00:00', 'out' => '17:35:00'],
            ['date' => '2026-07-31', 'in' => '08:00:00', 'break_start' => '13:00:00', 'break_end' => '14:00:00', 'out' => '17:00:00'],
            ['date' => '2026-08-02', 'in' => '09:00:00', 'break_start' => '13:00:00', 'break_end' => '13:30:00', 'out' => '15:00:00'],
            ['date' => '2026-08-03', 'in' => '08:00:00', 'break_start' => '13:00:00', 'break_end' => '14:00:00', 'out' => '17:00:00'],
            ['date' => '2026-08-04', 'in' => '08:02:00', 'break_start' => '13:00:00', 'break_end' => '14:00:00', 'out' => '17:00:00'],
            ['date' => '2026-08-05', 'in' => '08:00:00', 'break_start' => '13:00:00', 'break_end' => '14:00:00', 'out' => '17:00:00'],
        ]);

        $this->seedNightShiftEvents($action, $company, $workers['bruno'], 'bruno', [
            ['date' => '2026-07-20', 'in' => '22:00:00', 'break_start' => '02:00:00', 'break_end' => '02:30:00', 'out' => '06:00:00'],
            ['date' => '2026-07-21', 'in' => '22:02:00', 'break_start' => '02:00:00', 'break_end' => '02:30:00', 'out' => '06:03:00'],
            ['date' => '2026-07-22', 'in' => '22:00:00', 'break_start' => '02:00:00', 'break_end' => '02:30:00', 'out' => '06:00:00'],
            ['date' => '2026-07-23', 'in' => '22:00:00', 'break_start' => '02:00:00', 'break_end' => '02:30:00', 'out' => '06:00:00'],
            ['date' => '2026-07-24', 'in' => '22:00:00', 'break_start' => '02:00:00', 'break_end' => '02:30:00', 'out' => '06:10:00'],
            ['date' => '2026-07-27', 'in' => '22:00:00', 'break_start' => '02:00:00', 'break_end' => '02:30:00', 'out' => '06:00:00'],
            ['date' => '2026-07-28', 'in' => '22:00:00', 'break_start' => '02:00:00', 'break_end' => '02:30:00', 'out' => '06:00:00'],
            ['date' => '2026-07-29', 'in' => '22:04:00', 'break_start' => '02:00:00', 'break_end' => '02:30:00', 'out' => '06:00:00'],
            ['date' => '2026-07-30', 'in' => '22:00:00', 'break_start' => '02:00:00', 'break_end' => '02:30:00', 'out' => '06:00:00'],
            ['date' => '2026-07-31', 'in' => '22:00:00', 'break_start' => '02:00:00', 'break_end' => '02:30:00', 'out' => '06:00:00'],
            ['date' => '2026-08-03', 'in' => '22:00:00', 'break_start' => '02:00:00', 'break_end' => '02:30:00', 'out' => '06:00:00'],
            ['date' => '2026-08-04', 'in' => '22:00:00', 'break_start' => '02:00:00', 'break_end' => '02:30:00', 'out' => '06:00:00'],
        ]);

        $this->seedDayShiftEvents($action, $company, $workers['diego'], 'diego', 'kiosk', [
            ['date' => '2026-07-20', 'in' => '08:00:00', 'break_start' => '12:30:00', 'break_end' => '13:00:00', 'out' => '16:00:00'],
            ['date' => '2026-07-21', 'in' => '08:00:00', 'break_start' => '12:30:00', 'break_end' => '13:00:00', 'out' => '16:00:00'],
            ['date' => '2026-07-22', 'in' => '08:00:00', 'break_start' => '12:30:00', 'break_end' => '13:00:00', 'out' => '16:00:00'],
            ['date' => '2026-07-23', 'in' => '08:00:00', 'break_start' => '12:30:00', 'break_end' => '13:00:00', 'out' => '16:00:00'],
            ['date' => '2026-07-24', 'in' => '08:00:00', 'break_start' => '12:30:00', 'break_end' => '13:00:00', 'out' => '16:00:00'],
            ['date' => '2026-07-25', 'in' => '08:00:00', 'break_start' => '12:30:00', 'break_end' => '13:00:00', 'out' => '16:00:00'],
            ['date' => '2026-07-26', 'in' => '08:00:00', 'break_start' => '12:30:00', 'break_end' => '13:00:00', 'out' => '16:00:00'],
            ['date' => '2026-08-03', 'in' => '08:00:00', 'break_start' => '12:30:00', 'break_end' => '13:00:00', 'out' => '16:00:00'],
            ['date' => '2026-08-04', 'in' => '08:00:00', 'break_start' => '12:30:00', 'break_end' => '13:00:00', 'out' => '16:00:00'],
            ['date' => '2026-08-05', 'in' => '08:00:00', 'break_start' => '12:30:00', 'break_end' => '13:00:00', 'out' => '16:00:00'],
        ]);

        $this->event($action, $company, $workers['carla'], 'clock_in', 'admin_manual', '2026-08-04', '08:10:00', 'demo-manual-carla-2026-08-04-in', $sourceUser, [
            'reason' => 'Captura manual demo pendiente por olvido de registro.',
        ]);
        $this->event($action, $company, $workers['carla'], 'clock_out', 'admin_manual', '2026-08-04', '17:05:00', 'demo-manual-carla-2026-08-04-out', $sourceUser, [
            'reason' => 'Captura manual demo pendiente por cierre administrativo.',
        ]);
    }

    private function deleteObsoleteDemoTimeEvents(Company $company): void
    {
        TimeEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('idempotency_key', [
                'demo-web-ana-in',
                'demo-web-ana-break-start',
                'demo-web-ana-break-end',
                'demo-web-ana-out',
                'demo-kiosk-bruno-in',
                'demo-kiosk-bruno-break-start',
                'demo-kiosk-bruno-break-end',
                'demo-kiosk-bruno-out',
                'demo-manual-carla-in',
                'demo-manual-carla-out',
            ])
            ->delete();
    }

    /**
     * @param array{worker: Worker, relationship: EmploymentRelationship, center: Center} $context
     * @param array<int, array{date: string, in: string, break_start: string, break_end: string, out: string}> $days
     */
    private function seedDayShiftEvents(CreateTimeEventAction $action, Company $company, array $context, string $workerKey, string $source, array $days): void
    {
        foreach ($days as $day) {
            $prefix = "demo-{$source}-{$workerKey}-{$day['date']}";
            $this->event($action, $company, $context, 'clock_in', $source, $day['date'], $day['in'], "{$prefix}-in");
            $this->event($action, $company, $context, 'break_start', $source, $day['date'], $day['break_start'], "{$prefix}-break-start");
            $this->event($action, $company, $context, 'break_end', $source, $day['date'], $day['break_end'], "{$prefix}-break-end");
            $this->event($action, $company, $context, 'clock_out', $source, $day['date'], $day['out'], "{$prefix}-out");
        }
    }

    /**
     * @param array{worker: Worker, relationship: EmploymentRelationship, center: Center} $context
     * @param array<int, array{date: string, in: string, break_start: string, break_end: string, out: string}> $days
     */
    private function seedNightShiftEvents(CreateTimeEventAction $action, Company $company, array $context, string $workerKey, array $days): void
    {
        foreach ($days as $day) {
            $nextDate = CarbonImmutable::parse($day['date'])->addDay()->toDateString();
            $prefix = "demo-kiosk-{$workerKey}-{$day['date']}";
            $this->event($action, $company, $context, 'clock_in', 'kiosk', $day['date'], $day['in'], "{$prefix}-in");
            $this->event($action, $company, $context, 'break_start', 'kiosk', $nextDate, $day['break_start'], "{$prefix}-break-start");
            $this->event($action, $company, $context, 'break_end', 'kiosk', $nextDate, $day['break_end'], "{$prefix}-break-end");
            $this->event($action, $company, $context, 'clock_out', 'kiosk', $nextDate, $day['out'], "{$prefix}-out");
        }
    }

    /**
     * @param array{worker: Worker, relationship: EmploymentRelationship, center: Center} $context
     * @param array<string, mixed> $metadata
     */
    private function event(
        CreateTimeEventAction $action,
        Company $company,
        array $context,
        string $eventType,
        string $source,
        string $date,
        string $time,
        string $idempotencyKey,
        ?User $sourceUser = null,
        array $metadata = [],
    ): TimeEvent {
        $center = $context['center'];

        return $action->handle(
            $company,
            $context['worker'],
            [
                'event_type' => $eventType,
                'occurred_local_date' => $date,
                'occurred_local_time' => $time,
                'timezone' => $center->timezone,
                'received_at' => CarbonImmutable::parse($date.' '.$time, $center->timezone)->utc()->addMinute(),
                'source' => $source,
                'idempotency_key' => $idempotencyKey,
                'metadata' => ['demo' => true, 'channel' => $source] + $metadata,
            ],
            $context['relationship'],
            $center,
            $sourceUser,
        );
    }
}
