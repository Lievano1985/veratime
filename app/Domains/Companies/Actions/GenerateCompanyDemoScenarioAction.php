<?php

namespace App\Domains\Companies\Actions;

use App\Domains\Attendance\Actions\CreateAttendancePeriodAction;
use App\Domains\AttendanceIncidents\Actions\CreateAttendanceIncidentAction;
use App\Domains\Organization\Actions\AssignPrimaryOrganizationalUnitAction;
use App\Domains\Organization\Actions\CreateOrganizationalUnitAction;
use App\Domains\Scheduling\Actions\AssignScheduleProfileAction;
use App\Domains\Scheduling\Actions\CreateScheduleBatchAction;
use App\Domains\Scheduling\Actions\CreateScheduleProfileAction;
use App\Domains\Scheduling\Actions\CreateShiftTemplateAction;
use App\Domains\Scheduling\Actions\GenerateDraftScheduleBatchFromProfilesAction;
use App\Domains\Scheduling\Actions\PublishScheduleBatchAction;
use App\Domains\TimeRecords\Actions\CreateTimeEventAction;
use App\Domains\WorkDays\Actions\ProcessCompanyWorkDaysAction;
use App\Domains\Workers\Actions\CreateEmploymentRelationshipAction;
use App\Domains\Workers\Actions\CreateWorkerAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\CompanyDemoScenario;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerateCompanyDemoScenarioAction
{
    private const SCENARIO = 'company_onboarding_v1';

    public function __construct(
        private readonly CreateCenterAction $createCenter,
        private readonly CreateOrganizationalUnitAction $createUnit,
        private readonly CreateWorkerAction $createWorker,
        private readonly CreateEmploymentRelationshipAction $createRelationship,
        private readonly AssignPrimaryOrganizationalUnitAction $assignPrimaryUnit,
        private readonly CreateShiftTemplateAction $createShiftTemplate,
        private readonly CreateScheduleProfileAction $createProfile,
        private readonly AssignScheduleProfileAction $assignProfile,
        private readonly CreateScheduleBatchAction $createBatch,
        private readonly GenerateDraftScheduleBatchFromProfilesAction $generateBatch,
        private readonly PublishScheduleBatchAction $publishBatch,
        private readonly CreateTimeEventAction $createTimeEvent,
        private readonly CreateAttendanceIncidentAction $createIncident,
        private readonly ProcessCompanyWorkDaysAction $processWorkDays,
        private readonly CreateAttendancePeriodAction $createAttendancePeriod,
    ) {}

    public function handle(CompanyDemoScenario $scenario): CompanyDemoScenario
    {
        $scenario = CompanyDemoScenario::query()
            ->with('company.users')
            ->findOrFail($scenario->id);

        if ($scenario->status === CompanyDemoScenario::STATUS_COMPLETED) {
            return $scenario;
        }

        $claimed = CompanyDemoScenario::query()
            ->whereKey($scenario->id)
            ->whereIn('status', [CompanyDemoScenario::STATUS_PENDING, CompanyDemoScenario::STATUS_FAILED])
            ->update([
                'status' => CompanyDemoScenario::STATUS_PROCESSING,
                'error_message' => null,
                'started_at' => now(),
                'completed_at' => null,
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return $scenario->fresh() ?? $scenario;
        }

        $scenario->refresh()->load('company.users');

        $company = $scenario->company;
        if (! $company || $company->status !== 'active') {
            throw new InvalidArgumentException('El escenario demo requiere una empresa activa.');
        }

        $actor = $this->scenarioActor($company, $scenario);
        [$periodStart, $periodEnd] = $this->completedBiweeklyRange($company);

        $summary = DB::transaction(function () use ($company, $actor, $periodStart, $periodEnd): array {
            $startedAt = CarbonImmutable::parse($periodStart)->subMonth()->toDateString();
            $structure = $this->createStructure($company);
            $workers = $this->createWorkers($company, $structure, $startedAt);
            $profiles = $this->createSchedules($company, $periodStart, $workers, $structure, $actor);

            $this->publishTwoWeeks($company, $structure['centers'], $periodStart, $actor);
            $this->createApprovedAttendanceIncidents($company, $actor, $workers, $periodStart);
            $events = $this->createTimeEvents($company, $actor, $workers, $periodStart, $periodEnd);

            $workDays = $this->processWorkDays->handle(
                $company,
                $periodStart,
                $periodEnd,
                actor: $actor,
                mode: 'company_demo',
                reason: 'Calculo inicial del escenario de demostracion.',
                onlyPendingOrStale: false,
            );

            $periods = $this->createPeriods($company, $structure['centers'], $periodStart, $periodEnd, $actor);

            return [
                'schema_version' => 1,
                'scenario' => self::SCENARIO,
                'workers' => count($workers),
                'centers' => count($structure['centers']),
                'areas' => count($structure['units']),
                'shift_templates' => count($profiles['templates']),
                'schedule_profiles' => count($profiles['profiles']),
                'published_batches' => 4,
                'time_events' => $events,
                'attendance_periods' => count($periods),
                'work_days' => $workDays,
                'pending_review_cases' => [
                    'overtime_detected',
                    'twelve_hours_exceeded',
                ],
            ];
        });

        $scenario->forceFill([
            'status' => CompanyDemoScenario::STATUS_COMPLETED,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'summary' => $summary,
            'completed_at' => now(),
        ])->save();

        return $scenario->refresh();
    }

    /** @return array{0: string, 1: string} */
    private function completedBiweeklyRange(Company $company): array
    {
        $today = CarbonImmutable::now($company->timezone ?: 'America/Mexico_City')->startOfDay();
        $lastCompleteWeek = $today->startOfWeek(CarbonInterface::MONDAY)->subWeek();
        $start = $lastCompleteWeek->subWeek();

        return [$start->toDateString(), $lastCompleteWeek->endOfWeek(CarbonInterface::SUNDAY)->toDateString()];
    }

    private function scenarioActor(Company $company, CompanyDemoScenario $scenario): User
    {
        $requested = $scenario->requestedBy;
        if ($requested && $requested->status === 'active' && $requested->belongsToCompany($company)) {
            return $requested;
        }

        $actor = $company->activeUsers()->orderBy('users.id')->first();
        if (! $actor) {
            throw new InvalidArgumentException('La empresa demo requiere un administrador activo.');
        }

        return $actor;
    }

    /** @return array{centers: array<string, Center>, units: array<string, OrganizationalUnit>} */
    private function createStructure(Company $company): array
    {
        $metadata = $this->metadata();
        $day = $this->createCenter->handle($company, [
            'code' => 'DEMO-DIA', 'name' => 'Centro Diurno', 'timezone' => $company->timezone,
            'status' => 'active', 'metadata' => $metadata,
        ]);
        $night = $this->createCenter->handle($company, [
            'code' => 'DEMO-NOC', 'name' => 'Centro Nocturno', 'timezone' => $company->timezone,
            'status' => 'active', 'metadata' => $metadata,
        ]);

        return [
            'centers' => ['day' => $day, 'night' => $night],
            'units' => [
                'administration' => $this->createUnit->handle($company, $day, ['code' => 'ADM', 'name' => 'Administracion', 'type' => 'area', 'metadata' => $metadata]),
                'day_operations' => $this->createUnit->handle($company, $day, ['code' => 'OPS-DIA', 'name' => 'Operacion', 'type' => 'area', 'metadata' => $metadata]),
                'night_operations' => $this->createUnit->handle($company, $night, ['code' => 'OPS-NOC', 'name' => 'Operacion nocturna', 'type' => 'area', 'metadata' => $metadata]),
            ],
        ];
    }

    /** @return array<string, array{worker: Worker, relationship: EmploymentRelationship, center: Center, unit: OrganizationalUnit, pattern: string}> */
    private function createWorkers(Company $company, array $structure, string $startedAt): array
    {
        $definitions = [
            ['adm_1', 'DEMO-DIA-001', 'Andrea Demo Administracion', 'Analista administrativa', 'day', 'administration', 'administration'],
            ['adm_2', 'DEMO-DIA-002', 'Bruno Demo Administracion', 'Coordinador administrativo', 'day', 'administration', 'administration'],
            ['day_1', 'DEMO-DIA-003', 'Carla Demo Operacion', 'Operadora diurna', 'day', 'day_operations', 'day_operations'],
            ['day_2', 'DEMO-DIA-004', 'Diego Demo Operacion', 'Operador diurno', 'day', 'day_operations', 'day_operations'],
            ['day_3', 'DEMO-DIA-005', 'Elena Demo Operacion', 'Supervisora diurna', 'day', 'day_operations', 'day_operations'],
            ['mixed_1', 'DEMO-NOC-001', 'Fabian Demo Mixto', 'Operador mixto', 'night', 'night_operations', 'mixed'],
            ['mixed_2', 'DEMO-NOC-002', 'Gabriela Demo Mixto', 'Operadora mixta', 'night', 'night_operations', 'mixed'],
            ['mixed_3', 'DEMO-NOC-003', 'Hector Demo Mixto', 'Supervisor mixto', 'night', 'night_operations', 'mixed'],
            ['night_1', 'DEMO-NOC-004', 'Irene Demo Nocturno', 'Operadora nocturna', 'night', 'night_operations', 'nocturnal'],
            ['night_2', 'DEMO-NOC-005', 'Jorge Demo Nocturno', 'Operador nocturno', 'night', 'night_operations', 'nocturnal'],
        ];

        $workers = [];
        foreach ($definitions as [$key, $code, $name, $position, $centerKey, $unitKey, $pattern]) {
            $worker = $this->createWorker->handle($company, [
                'employee_code' => $code,
                'full_name' => $name,
                'status' => 'active',
                'source' => 'job',
                'external_id' => 'demo:'.$code,
                'metadata' => $this->metadata(),
            ]);
            $relationship = $this->createRelationship->handle($company, $worker, $structure['centers'][$centerKey], [
                'position_name' => $position,
                'started_at' => $startedAt,
                'status' => 'active',
                'source' => 'job',
                'external_id' => 'demo:relationship:'.$code,
                'metadata' => $this->metadata(),
            ]);
            $unit = $structure['units'][$unitKey];
            $this->assignPrimaryUnit->handle($company, $relationship, $unit, [
                'effective_from' => $startedAt,
                'source' => 'system',
                'reason' => 'Escenario de demostracion.',
                'metadata' => $this->metadata(),
            ]);
            $workers[$key] = compact('worker', 'relationship', 'unit', 'pattern') + ['center' => $structure['centers'][$centerKey]];
        }

        return $workers;
    }

    /** @return array{templates: array<string, mixed>, profiles: array<string, mixed>} */
    private function createSchedules(Company $company, string $effectiveFrom, array $workers, array $structure, User $actor): array
    {
        $templates = [
            'administration' => $this->createShiftTemplate->handle($company, ['code' => 'DEMO-ADM', 'name' => 'Administrativo 09:00 a 18:00', 'metadata' => $this->metadata()], $this->segments('09:00', '13:00', '14:00', '18:00')),
            'day_operations' => $this->createShiftTemplate->handle($company, ['code' => 'DEMO-OPS-DIA', 'name' => 'Operacion diurna 07:00 a 16:00', 'metadata' => $this->metadata()], $this->segments('07:00', '11:00', '12:00', '16:00')),
            'mixed' => $this->createShiftTemplate->handle($company, ['code' => 'DEMO-MIXTO', 'name' => 'Operacion mixta 14:00 a 22:00', 'metadata' => $this->metadata()], $this->segments('14:00', '18:00', '18:30', '22:00')),
            'nocturnal' => $this->createShiftTemplate->handle($company, ['code' => 'DEMO-NOC', 'name' => 'Operacion nocturna 22:00 a 05:30', 'metadata' => $this->metadata()], $this->nightSegments()),
        ];

        $profiles = [];
        foreach ($templates as $key => $template) {
            $profiles[$key] = $this->createProfile->handle($company, [
                'code' => 'DEMO-PERFIL-'.strtoupper($key),
                'name' => 'Demo '.match ($key) {
                    'administration' => 'Administracion',
                    'day_operations' => 'Operacion diurna',
                    'mixed' => 'Operacion mixta',
                    default => 'Operacion nocturna',
                },
                'profile_type' => 'pattern',
                'pattern_mode' => 'weekly',
                'metadata' => $this->metadata(),
            ], $this->weeklyRules($template->id));
        }

        foreach ([
            ['administration', 'organizational_unit', $structure['units']['administration']->id],
            ['day_operations', 'organizational_unit', $structure['units']['day_operations']->id],
            ['mixed', 'organizational_unit', $structure['units']['night_operations']->id],
        ] as [$profileKey, $scope, $scopeId]) {
            $this->assignProfile->handle($company, $profiles[$profileKey], [
                'assignment_scope' => $scope,
                'organizational_unit_id' => $scopeId,
                'effective_from' => $effectiveFrom,
                'source' => 'system',
                'reason' => 'Escenario de demostracion.',
                'metadata' => $this->metadata(),
            ], $actor);
        }

        foreach (['night_1', 'night_2'] as $workerKey) {
            $this->assignProfile->handle($company, $profiles['nocturnal'], [
                'assignment_scope' => 'employment_relationship',
                'employment_relationship_id' => $workers[$workerKey]['relationship']->id,
                'effective_from' => $effectiveFrom,
                'source' => 'system',
                'reason' => 'Turno nocturno directo del escenario demo.',
                'metadata' => $this->metadata(),
            ], $actor);
        }

        return compact('templates', 'profiles');
    }

    private function publishTwoWeeks(Company $company, array $centers, string $periodStart, User $actor): void
    {
        $firstMonday = CarbonImmutable::parse($periodStart);
        foreach ($centers as $center) {
            foreach ([$firstMonday, $firstMonday->addWeek()] as $monday) {
                $batch = $this->createBatch->handle($company, $center, [
                    'period_start' => $monday->toDateString(),
                    'creation_source' => 'profile',
                    'notes' => 'Programacion publicada por el escenario demo.',
                ], $actor);
                $this->generateBatch->handle($actor, $company, $batch);
                $this->publishBatch->handle($actor, $company, $batch);
            }
        }
    }

    private function createApprovedAttendanceIncidents(Company $company, User $actor, array $workers, string $periodStart): void
    {
        $start = CarbonImmutable::parse($periodStart);
        foreach ([
            ['adm_1', $start->addDay(), $start->addDays(2), 'vacation', 'paid', 'VAC-DEMO-01', 'Vacaciones aprobadas de demostracion.'],
            ['day_1', $start->addDays(3), $start->addDays(3), 'paid_permission', 'paid', 'PER-DEMO-01', 'Permiso especial pagado de demostracion.'],
            ['mixed_1', $start->addWeek()->addDays(3), $start->addWeek()->addDays(3), 'incapacity', 'not_applicable', 'INC-DEMO-01', 'Incapacidad de demostracion.'],
            ['day_3', $start->addWeek()->addDay(), $start->addWeek()->addDay(), 'unjustified_absence', 'unpaid', 'AUS-DEMO-01', 'Ausencia no justificada dictaminada para demostracion.'],
        ] as [$workerKey, $from, $to, $type, $payment, $reference, $notes]) {
            $this->createIncident->handle($company, $actor, [
                'worker_id' => $workers[$workerKey]['worker']->id,
                'start_date' => $from->toDateString(),
                'end_date' => $to->toDateString(),
                'incident_type' => $type,
                'payment_status' => $payment,
                'reference' => $reference,
                'notes' => $notes,
            ]);
        }
    }

    private function createTimeEvents(Company $company, User $actor, array $workers, string $periodStart, string $periodEnd): int
    {
        $absenceDates = [
            'adm_1' => [CarbonImmutable::parse($periodStart)->addDay()->toDateString(), CarbonImmutable::parse($periodStart)->addDays(2)->toDateString()],
            'day_1' => [CarbonImmutable::parse($periodStart)->addDays(3)->toDateString()],
            'mixed_1' => [CarbonImmutable::parse($periodStart)->addWeek()->addDays(3)->toDateString()],
            'day_3' => [CarbonImmutable::parse($periodStart)->addWeek()->addDay()->toDateString()],
        ];
        $count = 0;
        $date = CarbonImmutable::parse($periodStart);
        $end = CarbonImmutable::parse($periodEnd);

        while ($date->lte($end)) {
            if ($date->isWeekday()) {
                foreach ($workers as $key => $context) {
                    if (in_array($date->toDateString(), $absenceDates[$key] ?? [], true)) {
                        continue;
                    }

                    $isLegalAlertDemoDate = $date->isSameDay(CarbonImmutable::parse($periodStart)->addWeek()->addDays(1));
                    $times = $this->eventTimes($context['pattern'], $date, $key, $isLegalAlertDemoDate);
                    foreach ($times as [$eventType, $eventDate, $eventTime]) {
                        $this->createTimeEvent->handle($company, $context['worker'], [
                            'event_type' => $eventType,
                            'occurred_local_date' => $eventDate->toDateString(),
                            'occurred_local_time' => $eventTime,
                            'timezone' => $context['center']->timezone,
                            'source' => 'job',
                            'status' => 'valid',
                            'external_id' => sprintf('demo:%s:%s:%s', $context['worker']->employee_code, $date->toDateString(), $eventType),
                            'metadata' => $this->metadata(),
                        ], $context['relationship'], $context['center'], $actor);
                        $count++;
                    }
                }
            }
            $date = $date->addDay();
        }

        return $count;
    }

    /** @return list<array{0: string, 1: CarbonImmutable, 2: string}> */
    private function eventTimes(string $pattern, CarbonImmutable $date, string $workerKey, bool $isLegalAlertDemoDate): array
    {
        return match ($pattern) {
            'administration' => $this->breakEvents($date, '09:00', '13:00', '14:00', $workerKey === 'adm_2' && $isLegalAlertDemoDate ? '22:15' : '18:00'),
            'day_operations' => $this->breakEvents($date, '07:00', '11:00', '12:00', $workerKey === 'day_2' && $isLegalAlertDemoDate ? '18:00' : '16:00'),
            'mixed' => $this->breakEvents($date, '14:00', '18:00', '18:30', '22:00'),
            'nocturnal' => [
                ['clock_in', $date, '22:00'],
                ['break_start', $date->addDay(), '01:30'],
                ['break_end', $date->addDay(), '02:00'],
                ['clock_out', $date->addDay(), '05:30'],
            ],
        };
    }

    /** @return list<array{0: string, 1: CarbonImmutable, 2: string}> */
    private function breakEvents(CarbonImmutable $date, string $in, string $breakStart, string $breakEnd, string $out): array
    {
        return [
            ['clock_in', $date, $in],
            ['break_start', $date, $breakStart],
            ['break_end', $date, $breakEnd],
            ['clock_out', $date, $out],
        ];
    }

    /** @return list<mixed> */
    private function createPeriods(Company $company, array $centers, string $start, string $end, User $actor): array
    {
        $periods = [];
        foreach ($centers as $key => $center) {
            $periods[] = $this->createAttendancePeriod->handle($company, $center, [
                'name' => 'Periodo quincenal demo - '.($key === 'day' ? 'Centro Diurno' : 'Centro Nocturno'),
                'period_start' => $start,
                'period_end' => $end,
                'notes' => $key === 'day'
                    ? 'Incluye dos alertas laborales pendientes para probar el flujo de revision.'
                    : 'Periodo demo listo para validar, cerrar y exportar.',
            ], createdBy: $actor);
        }

        return $periods;
    }

    /** @return list<array<string, mixed>> */
    private function segments(string $start, string $breakStart, string $breakEnd, string $end): array
    {
        return [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => $start, 'end_local_time' => $breakStart, 'start_day_offset' => 0, 'end_day_offset' => 0, 'sort_order' => 1],
            ['segment_type' => 'break', 'timing_mode' => 'fixed', 'start_local_time' => $breakStart, 'end_local_time' => $breakEnd, 'start_day_offset' => 0, 'end_day_offset' => 0, 'is_paid' => false, 'sort_order' => 2],
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => $breakEnd, 'end_local_time' => $end, 'start_day_offset' => 0, 'end_day_offset' => 0, 'sort_order' => 3],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function nightSegments(): array
    {
        return [
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '22:00', 'end_local_time' => '01:30', 'start_day_offset' => 0, 'end_day_offset' => 1, 'sort_order' => 1],
            ['segment_type' => 'break', 'timing_mode' => 'fixed', 'start_local_time' => '01:30', 'end_local_time' => '02:00', 'start_day_offset' => 1, 'end_day_offset' => 1, 'is_paid' => false, 'sort_order' => 2],
            ['segment_type' => 'work', 'timing_mode' => 'fixed', 'start_local_time' => '02:00', 'end_local_time' => '05:30', 'start_day_offset' => 1, 'end_day_offset' => 1, 'sort_order' => 3],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function weeklyRules(int $shiftTemplateId): array
    {
        return collect(range(1, 7))->map(fn (int $day): array => [
            'day_of_week' => $day,
            'day_type' => $day <= 5 ? 'shift' : 'rest',
            'shift_template_id' => $day <= 5 ? $shiftTemplateId : null,
            'metadata' => $this->metadata(),
        ])->all();
    }

    /** @return array<string, mixed> */
    private function metadata(): array
    {
        return ['demo' => true, 'scenario' => self::SCENARIO];
    }
}
