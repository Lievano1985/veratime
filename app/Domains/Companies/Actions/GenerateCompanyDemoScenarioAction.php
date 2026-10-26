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
use App\Domains\WorkDays\Actions\ProcessSingleWorkDayAction;
use App\Domains\Workers\Actions\CreateEmploymentRelationshipAction;
use App\Domains\Workers\Actions\CreateWorkerAction;
use App\Models\Center;
use App\Models\Company;
use App\Models\CompanyDemoScenario;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\TimeEvent;
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
        $today = CarbonImmutable::now($company->timezone ?: 'America/Mexico_City')->startOfDay();
        $currentWeekStart = $today->startOfWeek(CarbonInterface::MONDAY);
        $currentWeekEnd = $currentWeekStart->endOfWeek(CarbonInterface::SUNDAY);
        $lastClosedDay = $today->subDay();

        $summary = DB::transaction(function () use ($company, $actor, $periodStart, $periodEnd, $currentWeekStart, $currentWeekEnd, $lastClosedDay): array {
            $startedAt = CarbonImmutable::parse($periodStart)->subMonth()->toDateString();
            $structure = $this->createStructure($company);
            $workers = $this->createWorkers($company, $structure, $startedAt);
            $profiles = $this->createSchedules($company, $periodStart, $workers, $structure, $actor);

            $this->publishThreeWeeks($company, $structure['centers'], $periodStart, $currentWeekStart, $actor);
            $this->createApprovedAttendanceIncidents($company, $actor, $workers, $periodStart, $currentWeekStart);
            $pendingReviewCases = $this->pendingReviewCases($periodStart, $lastClosedDay->toDateString());
            $events = $this->createTimeEvents(
                $company,
                $actor,
                $workers,
                $periodStart,
                $lastClosedDay->toDateString(),
                $pendingReviewCases,
            );

            $workDays = $this->processWorkDays->handle(
                $company,
                $periodStart,
                $lastClosedDay->toDateString(),
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
                'published_batches' => 6,
                'time_events' => $events,
                'attendance_periods' => count($periods),
                'work_days' => $workDays,
                'current_week' => [
                    'start' => $currentWeekStart->toDateString(),
                    'end' => $currentWeekEnd->toDateString(),
                    'last_closed_day' => $lastClosedDay->toDateString(),
                ],
                'pending_review_cases' => array_sum(array_map('count', $pendingReviewCases)),
            ];
        });

        $scenario->forceFill([
            'status' => CompanyDemoScenario::STATUS_COMPLETED,
            'period_start' => $periodStart,
            'period_end' => $currentWeekEnd->toDateString(),
            'summary' => $summary,
            'completed_at' => now(),
        ])->save();

        return $this->refreshCurrentDay($scenario->refresh());
    }

    public function refreshCurrentDay(CompanyDemoScenario $scenario): CompanyDemoScenario
    {
        if ($scenario->status !== CompanyDemoScenario::STATUS_COMPLETED) {
            throw new InvalidArgumentException('El demo debe estar completado para actualizar el dia actual.');
        }

        $company = $scenario->company;
        $actor = $this->scenarioActor($company, $scenario);
        $now = CarbonImmutable::now($company->timezone ?: 'America/Mexico_City');
        $date = $now->startOfDay();
        $patterns = [
            'DEMO-DIA-001' => 'administration', 'DEMO-DIA-002' => 'administration',
            'DEMO-DIA-003' => 'day_operations', 'DEMO-DIA-004' => 'day_operations', 'DEMO-DIA-005' => 'day_operations',
            'DEMO-NOC-001' => 'mixed', 'DEMO-NOC-002' => 'mixed', 'DEMO-NOC-003' => 'mixed',
            'DEMO-NOC-004' => 'nocturnal', 'DEMO-NOC-005' => 'nocturnal',
        ];

        return DB::transaction(function () use ($scenario, $company, $actor, $now, $date, $patterns): CompanyDemoScenario {
            $created = 0;
            $assignments = DailyScheduleAssignment::query()
                ->with('employmentRelationship.worker', 'employmentRelationship.center')
                ->where('company_id', $company->id)
                ->whereDate('work_date', $date->toDateString())
                ->whereIn('day_type', ['shift', 'flexible', 'on_call'])
                ->get();

            foreach ($assignments as $assignment) {
                $relationship = $assignment->employmentRelationship;
                $worker = $relationship?->worker;
                $pattern = $patterns[$worker?->employee_code ?? ''] ?? null;
                if (! $pattern || ! ($worker->metadata['demo'] ?? false)) {
                    continue;
                }

                $closed = false;
                $workerCreated = 0;
                foreach ($this->eventTimes($pattern, $date, null) as [$type, $eventDate, $time]) {
                    $occurredAt = CarbonImmutable::parse($eventDate->toDateString().' '.$time, $relationship->center->timezone);
                    if ($occurredAt->gt($now)) {
                        continue;
                    }
                    $event = $this->createTimeEvent->handle($company, $worker, [
                        'event_type' => $type,
                        'occurred_local_date' => $eventDate->toDateString(),
                        'occurred_local_time' => $time,
                        'timezone' => $relationship->center->timezone,
                        'source' => 'job',
                        'status' => 'valid',
                        'external_id' => sprintf('demo:%s:%s:%s', $worker->employee_code, $date->toDateString(), $type),
                        'metadata' => $this->metadata(),
                    ], $relationship, $relationship->center, $actor);
                    $created += $event->wasRecentlyCreated ? 1 : 0;
                    $workerCreated += $event->wasRecentlyCreated ? 1 : 0;
                    $closed = $closed || $type === 'clock_out';
                }

                if ($closed && $workerCreated > 0) {
                    app(ProcessSingleWorkDayAction::class)->handle($company, $relationship, $date, $actor,
                        reason: 'Jornada demo finalizada del dia actual.', mode: 'company_demo');
                }
            }

            $summary = $scenario->summary ?? [];
            $summary['current_day'] = ['date' => $date->toDateString(), 'updated_at' => $now->toIso8601String(), 'created_events' => $created];
            $summary['time_events'] = TimeEvent::query()->where('company_id', $company->id)
                ->where('metadata->scenario', self::SCENARIO)->count();
            $scenario->forceFill(['summary' => $summary])->save();

            return $scenario->refresh();
        });
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

    private function publishThreeWeeks(Company $company, array $centers, string $periodStart, CarbonImmutable $currentWeekStart, User $actor): void
    {
        $firstMonday = CarbonImmutable::parse($periodStart);
        foreach ($centers as $center) {
            foreach ([$firstMonday, $firstMonday->addWeek(), $currentWeekStart] as $monday) {
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

    private function createApprovedAttendanceIncidents(Company $company, User $actor, array $workers, string $periodStart, CarbonImmutable $currentWeekStart): void
    {
        $start = CarbonImmutable::parse($periodStart);
        foreach ([
            ['adm_1', $start->addDay(), $start->addDays(2), 'vacation', 'paid', 'VAC-DEMO-01', 'Vacaciones aprobadas de demostracion.'],
            ['day_1', $start->addDays(3), $start->addDays(3), 'paid_permission', 'paid', 'PER-DEMO-01', 'Permiso especial pagado de demostracion.'],
            ['mixed_1', $start->addWeek()->addDays(3), $start->addWeek()->addDays(3), 'incapacity', 'not_applicable', 'INC-DEMO-01', 'Incapacidad de demostracion.'],
            ['day_3', $start->addWeek()->addDay(), $start->addWeek()->addDay(), 'unjustified_absence', 'unpaid', 'AUS-DEMO-01', 'Ausencia no justificada dictaminada para demostracion.'],
            ['adm_2', $currentWeekStart->addDay(), $currentWeekStart->addDay(), 'paid_permission', 'paid', 'PER-DEMO-CURRENT-01', 'Permiso pagado de demostracion durante la semana en curso.'],
            ['mixed_2', $currentWeekStart->addDays(3), $currentWeekStart->addDays(3), 'unjustified_absence', 'unpaid', 'AUS-DEMO-CURRENT-01', 'Ausencia no justificada de demostracion durante la semana en curso.'],
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

    /**
     * @param  array<string, array<string, 'late_arrival'|'early_departure'|'extended_no_break'|'extended_no_break_late'|'incomplete'>>  $pendingReviewCases
     */
    private function createTimeEvents(Company $company, User $actor, array $workers, string $periodStart, string $periodEnd, array $pendingReviewCases): int
    {
        $currentWeekStart = CarbonImmutable::parse($periodEnd)->addDay()->startOfWeek(CarbonInterface::MONDAY);
        $absenceDates = [
            'adm_1' => [CarbonImmutable::parse($periodStart)->addDay()->toDateString(), CarbonImmutable::parse($periodStart)->addDays(2)->toDateString()],
            'day_1' => [CarbonImmutable::parse($periodStart)->addDays(3)->toDateString()],
            'mixed_1' => [CarbonImmutable::parse($periodStart)->addWeek()->addDays(3)->toDateString()],
            'day_3' => [CarbonImmutable::parse($periodStart)->addWeek()->addDay()->toDateString()],
            'adm_2' => [$currentWeekStart->addDay()->toDateString()],
            'mixed_2' => [$currentWeekStart->addDays(3)->toDateString()],
        ];
        $count = 0;
        $date = CarbonImmutable::parse($periodStart);
        $end = CarbonImmutable::parse($periodEnd);
        $supplementalWeekendWorkers = $this->supplementalWeekendWorkers($periodStart, $periodEnd);

        while ($date->lte($end)) {
            $workersForDate = $date->isWeekday()
                ? array_keys($workers)
                : array_values(array_unique([
                    ...array_keys($supplementalWeekendWorkers[$date->toDateString()] ?? []),
                    ...array_keys($pendingReviewCases[$date->toDateString()] ?? []),
                ]));

            foreach ($workersForDate as $key) {
                $context = $workers[$key];
                if ($date->isWeekday()) {
                    if (in_array($date->toDateString(), $absenceDates[$key] ?? [], true)) {
                        continue;
                    }
                }

                $times = $this->eventTimes(
                    $context['pattern'],
                    $date,
                    $pendingReviewCases[$date->toDateString()][$key] ?? null,
                );
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
            $date = $date->addDay();
        }

        return $count;
    }

    /** @return list<array{0: string, 1: CarbonImmutable, 2: string}> */
    private function eventTimes(string $pattern, CarbonImmutable $date, ?string $pendingReviewIssue): array
    {
        $times = match ($pattern) {
            'administration' => $this->breakEvents($date, '09:00', '13:00', '14:00', '18:00'),
            'day_operations' => $this->breakEvents($date, '07:00', '11:00', '12:00', '16:00'),
            'mixed' => $this->breakEvents($date, '14:00', '18:00', '18:30', '22:00'),
            'nocturnal' => [
                ['clock_in', $date, '22:00'],
                ['break_start', $date->addDay(), '01:30'],
                ['break_end', $date->addDay(), '02:00'],
                ['clock_out', $date->addDay(), '05:30'],
            ],
        };

        if ($pendingReviewIssue === 'late_arrival') {
            return $this->replaceEventTime($times, 'clock_in', match ($pattern) {
                'administration' => '09:25',
                'day_operations' => '07:25',
                'mixed' => '14:25',
                'nocturnal' => '22:25',
            });
        }

        if ($pendingReviewIssue === 'early_departure') {
            return $this->replaceEventTime($times, 'clock_out', match ($pattern) {
                'administration' => '17:15',
                'day_operations' => '15:15',
                'mixed' => '21:15',
                'nocturnal' => '05:00',
            });
        }

        if (in_array($pendingReviewIssue, ['extended_no_break', 'extended_no_break_late'], true)) {
            return [
                ['clock_in', $date, $pendingReviewIssue === 'extended_no_break_late' ? '07:25' : '07:00'],
                ['clock_out', $date, '21:30'],
            ];
        }

        if ($pendingReviewIssue === 'incomplete') {
            return array_values(array_filter($times, fn (array $event): bool => $event[0] !== 'clock_out'));
        }

        return $times;
    }

    /**
     * @param  list<array{0: string, 1: CarbonImmutable, 2: string}>  $times
     * @return list<array{0: string, 1: CarbonImmutable, 2: string}>
     */
    private function replaceEventTime(array $times, string $eventType, string $time): array
    {
        foreach ($times as $index => $event) {
            if ($event[0] === $eventType) {
                $times[$index][2] = $time;

                break;
            }
        }

        return $times;
    }

    /**
     * @return array<string, array<string, 'late_arrival'|'early_departure'|'extended_no_break'|'extended_no_break_late'|'incomplete'>>
     */
    private function pendingReviewCases(string $periodStart, string $lastClosedDay): array
    {
        $lastClosed = CarbonImmutable::parse($lastClosedDay);
        $previousWeekStart = $lastClosed->addDay()->startOfWeek(CarbonInterface::MONDAY)->subWeek();
        $start = CarbonImmutable::parse($periodStart);

        return collect([
            $lastClosed->toDateString() => [
                'adm_1' => 'late_arrival',
                'adm_2' => 'late_arrival',
                'day_1' => 'early_departure',
                'day_2' => 'extended_no_break',
                'day_3' => 'early_departure',
            ],
            $lastClosed->subDay()->toDateString() => [
                'adm_1' => 'incomplete',
                'adm_2' => 'incomplete',
            ],
            $previousWeekStart->toDateString() => ['day_2' => 'extended_no_break_late'],
            $previousWeekStart->addDay()->toDateString() => ['day_2' => 'extended_no_break'],
            $previousWeekStart->addDays(2)->toDateString() => ['day_2' => 'extended_no_break'],
            $previousWeekStart->addDays(3)->toDateString() => ['day_2' => 'extended_no_break'],
            $previousWeekStart->addDays(4)->toDateString() => [
                'adm_1' => 'late_arrival',
                'day_1' => 'early_departure',
                'day_3' => 'incomplete',
            ],
        ])
            ->filter(fn (array $cases, string $date): bool => $date >= $start->toDateString() && $date <= $lastClosed->toDateString())
            ->all();
    }

    /** @return array<string, array<string, true>> */
    private function supplementalWeekendWorkers(string $periodStart, string $periodEnd): array
    {
        $previousWeekStart = CarbonImmutable::parse($periodEnd)
            ->addDay()
            ->startOfWeek(CarbonInterface::MONDAY)
            ->subWeek();

        return collect([$previousWeekStart->addDays(5), $previousWeekStart->addDays(6)])
            ->filter(fn (CarbonImmutable $date): bool => $date->toDateString() >= $periodStart && $date->toDateString() <= $periodEnd)
            ->mapWithKeys(fn (CarbonImmutable $date): array => [$date->toDateString() => ['day_2' => true]])
            ->all();
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
                    ? 'Incluye alertas preventivas pendientes para practicar el flujo de revision.'
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
