<?php

namespace App\Domains\Dashboard\Actions;

use App\Domains\Organization\Support\ScopedOperationalAccess;
use App\Models\Alert;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\EmploymentUnitAssignment;
use App\Models\OrganizationalUnit;
use App\Models\TimeEvent;
use App\Models\User;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class BuildOperationalDashboardAction
{
    public function __construct(private readonly ScopedOperationalAccess $scopedAccess) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Company $company, User $user, string $date, ?int $centerId = null): array
    {
        $timezone = $company->setting?->default_timezone ?: $company->timezone;
        $localDate = CarbonImmutable::parse($date, $timezone)->toDateString();
        $access = $this->visibleAccess($company, $user, $localDate);

        if ($centerId && $access['filter_center_ids'] !== null && ! in_array($centerId, $access['filter_center_ids'], true)) {
            $centerId = null;
            $access['center_ids'] = [];
            $access['relationship_ids'] = [];
        }

        $relationships = EmploymentRelationship::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereHas('worker', fn (Builder $query) => $query->where('status', 'active'))
            ->whereDate('started_at', '<=', $localDate)
            ->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhereDate('ended_at', '>=', $localDate));
        $this->applyAccess($relationships, $access, $centerId);

        $activeWorkerCount = (clone $relationships)->distinct('worker_id')->count('worker_id');
        $relationshipIds = (clone $relationships)->pluck('id');

        $lastEvents = TimeEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('employment_relationship_id', $relationshipIds)
            ->where('status', 'valid')
            ->whereIn('event_type', ['clock_in', 'clock_out', 'break_start', 'break_end'])
            ->whereDate('occurred_local_date', '>=', CarbonImmutable::parse($localDate)->subDay()->toDateString())
            ->whereDate('occurred_local_date', '<=', $localDate)
            ->orderByDesc('occurred_at_utc')
            ->get(['worker_id', 'event_type'])
            ->unique('worker_id');

        $workingNow = $lastEvents->whereIn('event_type', ['clock_in', 'break_end'])->count();
        $onBreak = $lastEvents->where('event_type', 'break_start')->count();

        $scheduled = DailyScheduleAssignment::query()
            ->where('company_id', $company->id)
            ->whereDate('work_date', $localDate)
            ->whereIn('day_type', ['shift', 'flexible', 'on_call'])
            ->whereIn('employment_relationship_id', $relationshipIds)
            ->get(['employment_relationship_id', 'window_start_local_time']);
        $clockedInRelationships = TimeEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('employment_relationship_id', $scheduled->pluck('employment_relationship_id'))
            ->where('status', 'valid')
            ->where('event_type', 'clock_in')
            ->whereDate('occurred_local_date', $localDate)
            ->pluck('employment_relationship_id')
            ->flip();
        $now = CarbonImmutable::now($timezone);
        $tolerance = max(0, (int) ($company->setting?->late_arrival_tolerance_minutes ?? 0));
        $absencePending = 0;
        $pendingEntry = 0;

        foreach ($scheduled as $assignment) {
            if ($clockedInRelationships->has($assignment->employment_relationship_id)) {
                continue;
            }

            if (! $assignment->window_start_local_time) {
                $pendingEntry++;

                continue;
            }

            $threshold = CarbonImmutable::parse("{$localDate} {$assignment->window_start_local_time}", $timezone)->addMinutes($tolerance);
            $now->greaterThan($threshold) ? $absencePending++ : $pendingEntry++;
        }

        $alerts = Alert::query()
            ->with('alertType:id,category')
            ->where('company_id', $company->id)
            ->whereIn('status', Alert::OPEN_STATUSES)
            ->whereDate('detected_at', $localDate)
            ->when($centerId, fn (Builder $query) => $query->whereHas('workDay', fn (Builder $workDays) => $workDays->where('center_id', $centerId)))
            ->when($access['center_ids'] !== null, function (Builder $query) use ($access): void {
                $query->whereHas('workDay', function (Builder $workDays) use ($access): void {
                    $workDays->where(function (Builder $visible): void {
                        $visible->whereRaw('1 = 0');
                    });

                    if ($access['center_ids'] !== []) {
                        $workDays->orWhereIn('center_id', $access['center_ids']);
                    }
                    if ($access['relationship_ids'] !== []) {
                        $workDays->orWhereIn('employment_relationship_id', $access['relationship_ids']);
                    }
                });
            })
            ->get(['id', 'alert_type_id', 'rule_code', 'severity']);

        $countsByRule = $alerts->countBy('rule_code');
        $manualEntries = TimeEvent::query()
            ->where('company_id', $company->id)
            ->whereIn('employment_relationship_id', $relationshipIds)
            ->where('source', 'admin_manual')
            ->whereNotIn('status', ['voided', 'ignored'])
            ->whereDate('occurred_local_date', $localDate)
            ->count();
        $segments = [
            'attendance' => $alerts->filter(fn (Alert $alert) => in_array($alert->alertType?->category, ['attendance', 'event'], true))->count(),
            'work_day' => $alerts->filter(fn (Alert $alert) => in_array($alert->alertType?->category, ['daily', 'weekly'], true))->count(),
            'rest' => $alerts->filter(fn (Alert $alert) => $alert->alertType?->category === 'rest')->count(),
            'evidence' => $manualEntries,
        ];

        return [
            'date' => $localDate,
            'timezone' => $timezone,
            'access' => $access,
            'metrics' => [
                'active_workers' => $activeWorkerCount,
                'working_now' => $workingNow,
                'on_break' => $onBreak,
                'absence_pending' => $absencePending,
                'pending_entry' => $pendingEntry,
            ],
            'alerts' => [
                ['key' => 'incomplete_work_day', 'title' => 'Jornadas abiertas o incompletas', 'description' => 'Secuencias que requieren revisión operativa.', 'level' => 'critical', 'count' => (int) ($countsByRule['incomplete_work_day'] ?? 0)],
                ['key' => 'late_arrival_detected', 'title' => 'Entradas tardías', 'description' => 'Registros con retardo calculado.', 'level' => 'warning', 'count' => (int) ($countsByRule['late_arrival_detected'] ?? 0)],
                ['key' => 'scheduled_absence', 'title' => 'Ausencias por validar', 'description' => 'Sin entrada después de la tolerancia.', 'level' => 'high', 'count' => $absencePending],
                ['key' => 'manual', 'title' => 'Capturas manuales recientes', 'description' => 'Registros que conservan evidencia de su captura.', 'level' => 'informational', 'count' => $manualEntries],
                ['key' => 'sunday_work', 'title' => 'Registros en domingo', 'description' => 'Situaciones para revisión, no determinaciones definitivas.', 'level' => 'warning', 'count' => (int) ($countsByRule['sunday_work'] ?? 0)],
                ['key' => 'mandatory_rest_work', 'title' => 'Registros en descanso obligatorio', 'description' => 'Situaciones que requieren validación.', 'level' => 'high', 'count' => (int) ($countsByRule['mandatory_rest_work'] ?? 0)],
            ],
            'segments' => $segments,
        ];
    }

    /** @return array{center_ids: list<int>|null, relationship_ids: list<int>|null, filter_center_ids: list<int>|null} */
    private function visibleAccess(Company $company, User $user, string $date): array
    {
        if (in_array($user->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            return ['center_ids' => null, 'relationship_ids' => null, 'filter_center_ids' => null];
        }

        $scope = $this->scopedAccess->scope($user, $company, $date);
        $unitIds = $scope['organizational_unit_ids'];
        $relationshipIds = $unitIds === [] ? [] : EmploymentUnitAssignment::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->whereIn('organizational_unit_id', $unitIds)
            ->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->pluck('employment_relationship_id')->filter()->unique()->values()->all();
        $unitCenterIds = $unitIds === [] ? [] : OrganizationalUnit::query()
            ->where('company_id', $company->id)->whereIn('id', $unitIds)
            ->pluck('center_id')->filter()->unique()->values()->all();

        return [
            'center_ids' => $scope['center_ids'],
            'relationship_ids' => $relationshipIds,
            'filter_center_ids' => array_values(array_unique([...$scope['center_ids'], ...$unitCenterIds])),
        ];
    }

    /** @param array{center_ids: list<int>|null, relationship_ids: list<int>|null} $access */
    private function applyAccess(Builder $query, array $access, ?int $centerId): void
    {
        if ($access['center_ids'] !== null) {
            $query->where(function (Builder $visible) use ($access): void {
                if ($access['center_ids'] !== []) {
                    $visible->whereIn('center_id', $access['center_ids']);
                }
                if ($access['relationship_ids'] !== []) {
                    $method = $access['center_ids'] === [] ? 'whereIn' : 'orWhereIn';
                    $visible->{$method}('id', $access['relationship_ids']);
                }
                if ($access['center_ids'] === [] && $access['relationship_ids'] === []) {
                    $visible->whereRaw('1 = 0');
                }
            });
        }

        if ($centerId) {
            $query->where('center_id', $centerId);
        }
    }
}
