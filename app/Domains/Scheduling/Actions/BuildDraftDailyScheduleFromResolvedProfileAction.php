<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\ScheduleProfileAssignment;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class BuildDraftDailyScheduleFromResolvedProfileAction
{
    public function __construct(private BuildDailyScheduleSegmentsFromShiftTemplateAction $segmentsBuilder)
    {
    }

    /**
     * @param array<string, mixed> $profileResolution
     * @return array{data: array<string, mixed>, segments: list<array<string, mixed>>}
     */
    public function handle(
        Company $company,
        EmploymentRelationship $relationship,
        string $workDate,
        string $timezone,
        ?OrganizationalUnit $primaryUnit,
        array $profileResolution,
    ): array {
        $assignment = $profileResolution['assignment'] ?? null;

        if (! $assignment) {
            return $this->unassignedWithoutProfile($workDate, $timezone, $primaryUnit);
        }

        $ruleResolution = app(ResolveScheduleProfileRuleForDateAction::class)->handle($assignment, $workDate);

        return $this->fromRule($company, $relationship, $workDate, $timezone, $primaryUnit, $ruleResolution);
    }

    /**
     * @return array{data: array<string, mixed>, segments: list<array<string, mixed>>}
     */
    private function fromRule(
        Company $company,
        EmploymentRelationship $relationship,
        string $workDate,
        string $timezone,
        ?OrganizationalUnit $primaryUnit,
        array $ruleResolution,
    ): array {
        /** @var ScheduleProfileAssignment $assignment */
        $assignment = $ruleResolution['assignment'];
        $profile = $ruleResolution['profile'];
        $rule = $ruleResolution['rule'] ?? null;
        $shiftTemplate = $ruleResolution['shift_template'] ?? null;
        $sourceReference = $this->sourceReference($ruleResolution, null);

        try {
            if ($profile->profile_type === 'calendar') {
                return $this->unassignedFromProfile($workDate, $timezone, $primaryUnit, $sourceReference, 'calendar_requires_daily_definition');
            }

            if ($profile->profile_type === 'pattern') {
                if (($ruleResolution['day_type'] ?? null) === 'rest') {
                    return $this->rest($workDate, $timezone, $primaryUnit, $sourceReference);
                }

                if (($ruleResolution['day_type'] ?? null) !== 'shift' || ! $shiftTemplate) {
                    throw new InvalidArgumentException('El patron no resolvio una plantilla de turno valida.');
                }

                return [
                    'data' => $this->baseData($workDate, $timezone, $primaryUnit, 'shift', 'profile', array_replace($sourceReference, [
                        'shift_template_id' => $shiftTemplate->id,
                    ])) + [
                        'shift_template_id' => $shiftTemplate->id,
                    ],
                    'segments' => $this->segmentsBuilder->handle($shiftTemplate, $workDate, $timezone),
                ];
            }

            if ($profile->profile_type === 'flexible') {
                if (($ruleResolution['day_type'] ?? null) === 'rest') {
                    return $this->rest($workDate, $timezone, $primaryUnit, $sourceReference);
                }

                if (($ruleResolution['day_type'] ?? null) !== 'work') {
                    throw new InvalidArgumentException('La regla flexible no es valida para la fecha.');
                }

                return [
                    'data' => $this->baseData($workDate, $timezone, $primaryUnit, 'flexible', 'profile', $sourceReference) + [
                        'required_minutes' => $ruleResolution['required_minutes'],
                        'window_start_local_time' => $this->timeValue($ruleResolution['window_start_local_time'] ?? null),
                        'window_end_local_time' => $this->timeValue($ruleResolution['window_end_local_time'] ?? null),
                        'window_start_day_offset' => (int) ($ruleResolution['window_start_day_offset'] ?? 0),
                        'window_end_day_offset' => (int) ($ruleResolution['window_end_day_offset'] ?? 0),
                    ],
                    'segments' => [],
                ];
            }

            if ($profile->profile_type === 'on_call') {
                if (($ruleResolution['day_type'] ?? null) === 'rest') {
                    return $this->rest($workDate, $timezone, $primaryUnit, $sourceReference);
                }

                if (($ruleResolution['day_type'] ?? null) !== 'on_call') {
                    throw new InvalidArgumentException('La regla bajo demanda no es valida para la fecha.');
                }

                return [
                    'data' => $this->baseData($workDate, $timezone, $primaryUnit, 'on_call', 'profile', $sourceReference) + [
                        'availability_start_local_time' => $this->timeValue($ruleResolution['availability_start_local_time'] ?? null),
                        'availability_end_local_time' => $this->timeValue($ruleResolution['availability_end_local_time'] ?? null),
                        'availability_start_day_offset' => (int) ($ruleResolution['availability_start_day_offset'] ?? 0),
                        'availability_end_day_offset' => (int) ($ruleResolution['availability_end_day_offset'] ?? 0),
                        'max_work_minutes' => $ruleResolution['max_work_minutes'],
                    ],
                    'segments' => [],
                ];
            }
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException(sprintf(
                'Configuracion invalida al generar relacion %d para %s con perfil %d: %s',
                $relationship->id,
                $workDate,
                $profile->id,
                $exception->getMessage(),
            ), previous: $exception);
        }

        throw new InvalidArgumentException(sprintf(
            'Configuracion invalida al generar relacion %d para %s con perfil %d.',
            $relationship->id,
            $workDate,
            $profile->id,
        ));
    }

    private function rest(string $workDate, string $timezone, ?OrganizationalUnit $primaryUnit, array $sourceReference): array
    {
        return [
            'data' => $this->baseData($workDate, $timezone, $primaryUnit, 'rest', 'profile', $sourceReference),
            'segments' => [],
        ];
    }

    private function unassignedFromProfile(string $workDate, string $timezone, ?OrganizationalUnit $primaryUnit, array $sourceReference, string $reason): array
    {
        return [
            'data' => $this->baseData($workDate, $timezone, $primaryUnit, 'unassigned', 'profile', array_replace($sourceReference, ['reason' => $reason])),
            'segments' => [],
        ];
    }

    private function unassignedWithoutProfile(string $workDate, string $timezone, ?OrganizationalUnit $primaryUnit): array
    {
        return [
            'data' => $this->baseData($workDate, $timezone, $primaryUnit, 'unassigned', 'system', [
                'schema_version' => 1,
                'generator' => 'schedule_profile_generation',
                'schedule_profile_id' => null,
                'schedule_profile_assignment_id' => null,
                'assignment_origin_type' => null,
                'assignment_origin_id' => null,
                'profile_type' => null,
                'pattern_mode' => null,
                'resolved_rule_type' => null,
                'resolved_rule_id' => null,
                'cycle_day' => null,
                'shift_template_id' => null,
                'reason' => 'no_effective_schedule_profile',
            ]),
            'segments' => [],
        ];
    }

    private function baseData(
        string $workDate,
        string $timezone,
        ?OrganizationalUnit $primaryUnit,
        string $dayType,
        string $sourceType,
        array $sourceReference,
    ): array {
        return [
            'work_date' => $workDate,
            'day_type' => $dayType,
            'timezone' => $timezone,
            'organizational_unit_id' => $primaryUnit?->id,
            'source_type' => $sourceType,
            'source_reference' => $sourceReference,
            'metadata' => ['generated_by' => 'schedule_profile_generation'],
        ];
    }

    private function sourceReference(array $ruleResolution, ?string $reason): array
    {
        /** @var ScheduleProfileAssignment $assignment */
        $assignment = $ruleResolution['assignment'];
        $profile = $ruleResolution['profile'];
        $rule = $ruleResolution['rule'] ?? null;
        $shiftTemplate = $ruleResolution['shift_template'] ?? null;

        return [
            'schema_version' => 1,
            'generator' => 'schedule_profile_generation',
            'schedule_profile_id' => $profile->id,
            'schedule_profile_assignment_id' => $assignment->id,
            'assignment_origin_type' => $assignment->assignment_scope,
            'assignment_origin_id' => match ($assignment->assignment_scope) {
                'company' => $assignment->company_id,
                'center' => $assignment->center_id,
                'organizational_unit' => $assignment->organizational_unit_id,
                'employment_relationship' => $assignment->employment_relationship_id,
                default => null,
            },
            'profile_type' => $profile->profile_type,
            'pattern_mode' => $profile->pattern_mode,
            'resolved_rule_type' => $ruleResolution['resolved_rule_type'] ?? null,
            'resolved_rule_id' => $rule?->id,
            'cycle_day' => $ruleResolution['cycle_day'] ?? null,
            'shift_template_id' => $shiftTemplate?->id,
            'reason' => $reason,
        ];
    }

    private function timeValue(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('H:i:s');
        }

        $value = (string) $value;

        return strlen($value) === 5 ? $value.':00' : $value;
    }
}
