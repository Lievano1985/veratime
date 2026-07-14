<?php

namespace Database\Seeders;

use App\Domains\MandatoryRestDays\Actions\CreateMandatoryRestDayAction;
use App\Domains\Schedules\Actions\CreateScheduleAssignmentAction;
use App\Domains\Schedules\Actions\SaveScheduleDaysAction;
use App\Domains\TimeRecords\Actions\CreateTimeEventAction;
use App\Domains\Workers\Actions\CreateOrUpdateWorkerCredentialAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\EmploymentRelationship;
use App\Models\LaborCondition;
use App\Models\MandatoryRestDay;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\ScheduleBreak;
use App\Models\ScheduleDay;
use App\Models\TimeEvent;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class VeraTimeDemoSeeder extends Seeder
{
    private const COMPANY_TAX_ID = 'VTD260712XX1';
    private const DEMO_PASSWORD = 'VeraDemo123!';
    private const DEMO_PIN = '1234';

    public function run(): void
    {
        $company = $this->company();
        $users = $this->users($company);
        $centers = $this->centers($company);
        $schedules = $this->schedules($company);
        $workers = $this->workers($company, $centers, $schedules);

        $this->mandatoryRestDays($company);
        $this->timeEvents($company, $users['rh'], $workers);
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
     * @return array{owner: User, admin: User, rh: User}
     */
    private function users(Company $company): array
    {
        $roles = [
            'owner' => $this->role('owner', 'Propietario'),
            'admin' => $this->role('admin', 'Administrador'),
            'rh' => $this->role('rh', 'Recursos Humanos'),
        ];

        return [
            'owner' => $this->user($company, $roles['owner'], 'Demo Owner', 'owner.demo@veratime.local', true),
            'admin' => $this->user($company, $roles['admin'], 'Demo Admin', 'admin.demo@veratime.local'),
            'rh' => $this->user($company, $roles['rh'], 'Demo RH', 'rh.demo@veratime.local'),
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

    /**
     * @return array<string, array{worker: Worker, relationship: EmploymentRelationship, center: Center}>
     */
    private function workers(Company $company, array $centers, array $schedules): array
    {
        $data = [
            'ana' => ['VT-001', 'Ana Demo Lopez', 'ana.demo@veratime.local', $centers['matriz'], $schedules['diurno'], 'Analista RH'],
            'bruno' => ['VT-002', 'Bruno Demo Perez', 'bruno.demo@veratime.local', $centers['planta'], $schedules['nocturno'], 'Operador nocturno'],
            'carla' => ['VT-003', 'Carla Demo Ruiz', 'carla.demo@veratime.local', $centers['matriz'], $schedules['diurno'], 'Supervisora demo'],
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
                    'started_at' => '2026-08-01',
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
            ->whereDate('effective_from', '2026-08-01')
            ->first() ?? new LaborCondition();

        $condition->fill([
            'employment_relationship_id' => $relationship->id,
            'schedule_id' => $schedule->id,
            'work_modality' => 'onsite',
            'weekly_hours' => 48,
            'rest_day_of_week' => 0,
            'policy_id' => null,
            'effective_from' => '2026-08-01',
            'effective_to' => null,
            'status' => 'active',
            'metadata' => ['demo' => true],
        ]);
        $condition->company()->associate($company);
        $condition->save();
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

        $this->event($action, $company, $workers['ana'], 'clock_in', 'web', '2026-08-17', '08:00:00', 'demo-web-ana-in');
        $this->event($action, $company, $workers['ana'], 'break_start', 'web', '2026-08-17', '13:00:00', 'demo-web-ana-break-start');
        $this->event($action, $company, $workers['ana'], 'break_end', 'web', '2026-08-17', '14:00:00', 'demo-web-ana-break-end');
        $this->event($action, $company, $workers['ana'], 'clock_out', 'web', '2026-08-17', '17:00:00', 'demo-web-ana-out');

        $this->event($action, $company, $workers['bruno'], 'clock_in', 'kiosk', '2026-08-17', '22:00:00', 'demo-kiosk-bruno-in');
        $this->event($action, $company, $workers['bruno'], 'break_start', 'kiosk', '2026-08-18', '02:00:00', 'demo-kiosk-bruno-break-start');
        $this->event($action, $company, $workers['bruno'], 'break_end', 'kiosk', '2026-08-18', '02:30:00', 'demo-kiosk-bruno-break-end');
        $this->event($action, $company, $workers['bruno'], 'clock_out', 'kiosk', '2026-08-18', '06:00:00', 'demo-kiosk-bruno-out');

        $this->event($action, $company, $workers['carla'], 'clock_in', 'admin_manual', '2026-08-17', '08:10:00', 'demo-manual-carla-in', $sourceUser, [
            'reason' => 'Captura manual demo por olvido de registro.',
        ]);
        $this->event($action, $company, $workers['carla'], 'clock_out', 'admin_manual', '2026-08-17', '17:05:00', 'demo-manual-carla-out', $sourceUser, [
            'reason' => 'Captura manual demo por cierre administrativo.',
        ]);
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
