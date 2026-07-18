<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\ScheduleProfileAssignment;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ResolveScheduleProfileRuleForDateAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(ScheduleProfileAssignment $assignment, string $date): array
    {
        $workDate = CarbonImmutable::parse($date)->startOfDay();
        $effectiveFrom = CarbonImmutable::parse($assignment->effective_from)->startOfDay();

        if ($workDate->lt($effectiveFrom)) {
            throw new InvalidArgumentException('La fecha no puede ser anterior al inicio efectivo de la asignacion.');
        }

        $profile = $assignment->scheduleProfile()->with([
            'weeklyRules.shiftTemplate.segments',
            'cycleRules.shiftTemplate.segments',
            'flexibleRules',
            'onCallRules',
        ])->firstOrFail();

        return match ($profile->profile_type) {
            'pattern' => $this->resolvePattern($assignment, $profile, $workDate, $effectiveFrom),
            'calendar' => $this->baseResult($assignment, $profile, $workDate) + [
                'resolved_rule_type' => 'calendar',
                'day_type' => 'manual_schedule_required',
                'rule' => null,
                'shift_template' => null,
            ],
            'flexible' => $this->resolveFlexible($assignment, $profile, $workDate),
            'on_call' => $this->resolveOnCall($assignment, $profile, $workDate),
            default => throw new InvalidArgumentException('El tipo de perfil no es valido.'),
        };
    }

    private function resolvePattern(ScheduleProfileAssignment $assignment, mixed $profile, CarbonImmutable $workDate, CarbonImmutable $effectiveFrom): array
    {
        if ($profile->pattern_mode === 'weekly') {
            $rule = $profile->weeklyRules->firstWhere('day_of_week', $workDate->isoWeekday());
            if (! $rule) {
                throw new InvalidArgumentException('No existe regla semanal para la fecha solicitada.');
            }

            return $this->baseResult($assignment, $profile, $workDate) + [
                'resolved_rule_type' => 'weekly',
                'rule' => $rule,
                'shift_template' => $rule->shiftTemplate,
                'day_type' => $rule->day_type,
                'cycle_day' => null,
            ];
        }

        if ($profile->pattern_mode !== 'cycle') {
            throw new InvalidArgumentException('La modalidad del patron no es valida.');
        }

        $cycleLength = $profile->cycleRules->count();
        if ($cycleLength < 2) {
            throw new InvalidArgumentException('El ciclo no esta configurado.');
        }

        $cycleDay = ($effectiveFrom->diffInDays($workDate) % $cycleLength) + 1;
        $rule = $profile->cycleRules->firstWhere('cycle_day', $cycleDay);
        if (! $rule) {
            throw new InvalidArgumentException('No existe regla del ciclo para la fecha solicitada.');
        }

        return $this->baseResult($assignment, $profile, $workDate) + [
            'resolved_rule_type' => 'cycle',
            'rule' => $rule,
            'shift_template' => $rule->shiftTemplate,
            'day_type' => $rule->day_type,
            'cycle_day' => $cycleDay,
        ];
    }

    private function resolveFlexible(ScheduleProfileAssignment $assignment, mixed $profile, CarbonImmutable $workDate): array
    {
        $rule = $profile->flexibleRules->firstWhere('day_of_week', $workDate->isoWeekday());
        if (! $rule) {
            throw new InvalidArgumentException('No existe regla flexible para la fecha solicitada.');
        }

        return $this->baseResult($assignment, $profile, $workDate) + [
            'resolved_rule_type' => 'flexible',
            'rule' => $rule,
            'shift_template' => null,
            'day_type' => $rule->day_type,
            'cycle_day' => null,
            'required_minutes' => $rule->required_minutes,
            'window_start_local_time' => $rule->window_start_local_time,
            'window_end_local_time' => $rule->window_end_local_time,
            'window_start_day_offset' => $rule->window_start_day_offset,
            'window_end_day_offset' => $rule->window_end_day_offset,
        ];
    }

    private function resolveOnCall(ScheduleProfileAssignment $assignment, mixed $profile, CarbonImmutable $workDate): array
    {
        $rule = $profile->onCallRules->firstWhere('day_of_week', $workDate->isoWeekday());
        if (! $rule) {
            throw new InvalidArgumentException('No existe regla bajo demanda para la fecha solicitada.');
        }

        return $this->baseResult($assignment, $profile, $workDate) + [
            'resolved_rule_type' => 'on_call',
            'rule' => $rule,
            'shift_template' => null,
            'day_type' => $rule->day_type,
            'cycle_day' => null,
            'availability_start_local_time' => $rule->availability_start_local_time,
            'availability_end_local_time' => $rule->availability_end_local_time,
            'availability_start_day_offset' => $rule->availability_start_day_offset,
            'availability_end_day_offset' => $rule->availability_end_day_offset,
            'max_work_minutes' => $rule->max_work_minutes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function baseResult(ScheduleProfileAssignment $assignment, mixed $profile, CarbonImmutable $workDate): array
    {
        return [
            'profile_type' => $profile->profile_type,
            'pattern_mode' => $profile->pattern_mode,
            'profile' => $profile,
            'assignment' => $assignment,
            'work_date' => $workDate->toDateString(),
            'assignment_scope' => $assignment->assignment_scope,
            'assignment_effective_from' => $assignment->effective_from,
        ];
    }
}
