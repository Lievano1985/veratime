<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Organization\Actions\ResolveEmploymentUnitsForDateAction;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use App\Models\ScheduleProfile;
use App\Models\ScheduleProfileAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Orchestrates the alternative planner using the existing scheduling domain. */
class ApplyPlannerScheduleAction
{
    public function handle(User $actor, Company $company, ScheduleBatch $batch, array $input): array
    {
        Gate::forUser($actor)->authorize('update', $batch);
        Validator::make($input, [
            'relationships' => ['required', 'array', 'min:1'],
            'relationships.*' => ['required', 'integer', 'distinct'],
            'mode' => ['required', 'in:manual,weekly,cycle,existing'],
            'rules' => ['required_unless:mode,existing', 'array', 'max:366'],
            'rules.*.value' => ['required', 'regex:/^(rest|[1-9][0-9]*)$/'],
            'rules.*.day' => ['required_unless:mode,existing', 'integer', 'min:1', 'max:366', 'distinct'],
            'effective_from' => ['required_unless:mode,manual', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'name' => ['required_if:mode,weekly,cycle', 'nullable', 'string', 'max:255'],
            'profile_id' => ['required_if:mode,existing', 'nullable', 'integer'],
            'replace_days' => ['required', 'boolean'],
            'replace_habitual' => ['required', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($actor, $company, $batch, $input): array {
            $batch = ScheduleBatch::query()->where('company_id', $company->id)->lockForUpdate()->findOrFail($batch->id);
            Gate::forUser($actor)->authorize('update', $batch);
            if ($batch->status !== 'draft') {
                throw new InvalidArgumentException('La semana debe estar en borrador.');
            }
            // Serialize planner writes and profile assignment changes for these people.
            $relationships = EmploymentRelationship::query()->where('company_id', $company->id)
                ->where('center_id', $batch->center_id)->whereIn('id', $input['relationships'])
                ->lockForUpdate()->get();
            if ($relationships->count() !== count($input['relationships'])) {
                throw new InvalidArgumentException('La selección contiene personas de otra empresa o centro.');
            }
            $mode = $input['mode'];
            $rules = collect($input['rules'] ?? [])->keyBy('day');
            if (in_array($mode, ['manual', 'weekly'], true) && $rules->keys()->sort()->values()->all() !== range(1, 7)) {
                throw new InvalidArgumentException('Completa los siete días, incluyendo descansos.');
            }
            if ($mode !== 'manual' && $batch->previous_batch_id) {
                throw new InvalidArgumentException('En una corrección cambia solo esta semana; el horario habitual se configura en una semana nueva.');
            }
            if ($mode !== 'manual' && ($input['effective_from'] > $batch->period_end->toDateString()
                || (filled($input['effective_to'] ?? null) && $input['effective_to'] < $batch->period_start->toDateString()))) {
                throw new InvalidArgumentException('La vigencia debe incluir al menos un día de la semana abierta.');
            }

            $profile = null;
            if ($mode !== 'manual') {
                Gate::forUser($actor)->authorize('create', [ScheduleProfile::class, $company]);
                if ($mode === 'existing') {
                    $profile = ScheduleProfile::query()->where('company_id', $company->id)->where('status', 'active')->findOrFail($input['profile_id']);
                    Gate::forUser($actor)->authorize('view', $profile);
                } else {
                    $normalized = $rules->values()->map(fn ($rule) => [
                        $mode === 'cycle' ? 'cycle_day' : 'day_of_week' => (int) $rule['day'],
                        'day_type' => $rule['value'] === 'rest' ? 'rest' : 'shift',
                        'shift_template_id' => $rule['value'] === 'rest' ? null : (int) $rule['value'],
                    ])->all();
                    $profile = app(CreateScheduleProfileAction::class)->handle($company, [
                        'code' => 'PLAN-'.Str::upper((string) Str::ulid()), 'name' => $input['name'],
                        'profile_type' => 'pattern', 'pattern_mode' => $mode,
                    ], $mode === 'weekly' ? $normalized : []);
                    if ($mode === 'cycle') {
                        app(ReplaceScheduleProfileCycleRulesAction::class)->handle($company, $profile, $normalized);
                    }
                }
                foreach ($relationships as $relationship) {
                    Gate::forUser($actor)->authorize('assign', [ScheduleProfile::class, $company, 'employment_relationship', $relationship, $input['effective_from']]);
                    $overlaps = ScheduleProfileAssignment::query()->where('company_id', $company->id)
                        ->where('employment_relationship_id', $relationship->id)->where('status', 'active')
                        ->whereDate('effective_from', '<=', $input['effective_to'] ?? '9999-12-31')
                        ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $input['effective_from']))
                        ->lockForUpdate()->get();
                    $data = ['assignment_scope' => 'employment_relationship', 'employment_relationship_id' => $relationship->id,
                        'effective_from' => $input['effective_from'], 'effective_to' => $input['effective_to'] ?? null,
                        'reason' => 'Asignación desde el planificador'];
                    if ($overlaps->isNotEmpty()) {
                        if (! $input['replace_habitual'] || $overlaps->count() !== 1) {
                            throw new InvalidArgumentException('Hay horarios habituales asignados. Revisa la selección y autoriza su reemplazo desde la nueva vigencia.');
                        }
                        app(ReplaceScheduleProfileAssignmentAction::class)->handle($company, $overlaps->first(), $profile, $data, $actor);
                    } else {
                        app(AssignScheduleProfileAction::class)->handle($company, $profile, $data, $actor);
                    }
                }
            }
            $changed = 0;
            $preserved = 0;
            $existing = $batch->dailyAssignments()->get()->keyBy(fn ($day) => $day->employment_relationship_id.'|'.$day->work_date->toDateString());
            foreach ($relationships as $relationship) {
                for ($day = 0; $day < 7; $day++) {
                    $date = CarbonImmutable::parse($batch->period_start)->addDays($day)->toDateString();
                    $current = $existing->get($relationship->id.'|'.$date);
                    if (! $relationship->isEffectiveOn($date) && ! ($batch->previous_batch_id && $current)) {
                        continue;
                    }
                    if ($mode !== 'manual' && ($date < $input['effective_from'] || (filled($input['effective_to'] ?? null) && $date > $input['effective_to']))) {
                        continue;
                    }
                    if ($current && $current->day_type !== 'unassigned' && ! $input['replace_days']) {
                        $preserved++;

                        continue;
                    }
                    if ($mode === 'manual') {
                        $value = $rules[$day + 1]['value'];
                        app(BulkReplaceDraftDailyScheduleAssignmentsAction::class)->handle($company, $batch, [$relationship->id], [$date], [
                            'day_type' => $value === 'rest' ? 'rest' : 'shift',
                            'shift_template_id' => $value === 'rest' ? null : (int) $value, 'source_type' => 'manual',
                            'organizational_unit_id' => app(ResolveEmploymentUnitsForDateAction::class)->handle($company, $relationship, $date)['primary']?->id,
                        ]);
                    } else {
                        $resolution = app(ResolveScheduleProfileForRelationshipAction::class)->handle($company, $relationship, $date);
                        $unit = app(ResolveEmploymentUnitsForDateAction::class)->handle($company, $relationship, $date)['primary'];
                        $draft = app(BuildDraftDailyScheduleFromResolvedProfileAction::class)->handle($company, $relationship, $date, $batch->center->timezone, $unit, $resolution);
                        app(ReplaceDraftDailyScheduleAssignmentAction::class)->handle($company, $batch, $relationship, $draft['data'], $draft['segments']);
                    }
                    $changed++;
                }
            }

            return ['changed' => $changed, 'preserved' => $preserved, 'profile_id' => $profile?->id];
        });
    }
}
