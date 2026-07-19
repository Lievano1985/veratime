<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\ScheduleBatchPublicationValidationResult;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\DailyScheduleSegment;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use App\Models\User;
use App\Support\RoleKey;
use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

class ValidateScheduleBatchForPublicationAction
{
    public function __construct(
        private ResolveScheduleBatchExpectedRelationshipDatesAction $expectedRelationshipDates,
        private ValidateDailyScheduleAssignmentAction $dailyAssignmentValidator,
    ) {
    }

    public function handle(User $actor, Company $company, ScheduleBatch $batch): ScheduleBatchPublicationValidationResult
    {
        $batch = ScheduleBatch::query()
            ->with([
                'company',
                'center',
                'dailyAssignments' => fn ($query) => $query
                    ->with(['employmentRelationship.worker', 'employmentRelationship.center', 'shiftTemplate', 'segments'])
                    ->orderBy('work_date')
                    ->orderBy('employment_relationship_id'),
            ])
            ->findOrFail($batch->id);

        $result = new ScheduleBatchPublicationValidationResult($batch);
        $this->validateBatchContext($result, $actor, $company, $batch);

        $expected = $this->expectedRelationshipDates->handle($company, $batch);
        $expectedMap = [];
        foreach ($expected as $item) {
            $result->relationshipsExpected++;
            foreach ($item['dates'] as $workDate) {
                $expectedMap[$this->key($item['relationship']->id, $workDate)] = $item['relationship'];
                $result->datesExpected++;
                $result->assignmentsExpected++;
            }
        }

        if ($result->relationshipsExpected === 0) {
            $result->addError('No existen relaciones laborales aplicables para el centro y periodo.');
        }

        if ($batch->dailyAssignments->isEmpty()) {
            $result->addError('El lote no contiene programacion diaria.');
        }

        $foundMap = [];
        foreach ($batch->dailyAssignments as $assignment) {
            $result->assignmentsFound++;
            $workDate = $assignment->work_date->toDateString();
            $key = $this->key($assignment->employment_relationship_id, $workDate);
            $foundMap[$key] = $assignment;

            $this->validateAssignmentContext($result, $company, $batch, $assignment);
            $result->countDayType($assignment->day_type);

            if ($assignment->day_type === 'unassigned') {
                $result->assignmentsUnassigned++;
                $result->addError('Existen dias pendientes de definicion.');
            }

            if (! array_key_exists($key, $expectedMap)) {
                $result->addError('Existe programacion que no corresponde al centro, periodo o vigencia esperada.');
                continue;
            }

            $relationship = $expectedMap[$key];
            $this->validateAssignmentShape($result, $company, $batch, $relationship, $assignment);
        }

        foreach (array_keys($expectedMap) as $key) {
            if (! array_key_exists($key, $foundMap)) {
                $result->assignmentsMissing++;
                $result->addError('Falta programacion para una relacion laboral dentro del periodo.');
            }
        }

        $this->validatePublishedConflicts($result, $batch);
        $result->snapshotReady = $result->valid()
            && $result->assignmentsExpected > 0
            && $result->assignmentsExpected === $result->assignmentsFound
            && $result->assignmentsUnassigned === 0;

        return $result;
    }

    private function validateBatchContext(ScheduleBatchPublicationValidationResult $result, User $actor, Company $company, ScheduleBatch $batch): void
    {
        if ($company->status !== 'active') {
            $result->addError('La empresa debe estar activa para publicar programacion.');
        }

        if ($actor->status !== 'active'
            || ! $actor->belongsToCompany($company)
            || ! in_array($actor->roleKeyForCompany($company), RoleKey::companyManagers(), true)) {
            $result->addError('El usuario no puede publicar programacion diaria para esta empresa.');
        }

        if ($batch->company_id !== $company->id || $batch->company?->id !== $company->id) {
            $result->addError('El lote no pertenece a la empresa activa.');
        }

        if (! $batch->center || $batch->center->company_id !== $company->id || $batch->center->status !== 'active') {
            $result->addError('El centro del lote no pertenece a la empresa activa.');
        }

        if ($batch->status !== 'draft') {
            $result->addError('Solo un lote en borrador puede publicarse.');
        }

        if ((int) $batch->version !== 1) {
            $result->addError('En F3A solo puede publicarse la version inicial del lote.');
        }

        if ($batch->previous_batch_id !== null) {
            $result->addError('En F3A no se publican lotes correctivos.');
        }

        if ($batch->period_end->lt($batch->period_start)) {
            $result->addError('El periodo del lote no es valido.');
        }
    }

    private function validateAssignmentContext(
        ScheduleBatchPublicationValidationResult $result,
        Company $company,
        ScheduleBatch $batch,
        DailyScheduleAssignment $assignment,
    ): void {
        if ($assignment->company_id !== $company->id) {
            $result->addError('Existe programacion diaria de otra empresa.');
        }

        if ($assignment->schedule_batch_id !== $batch->id) {
            $result->addError('Existe programacion diaria asociada a otro lote.');
        }

        $workDate = $assignment->work_date->toDateString();
        if ($workDate < $batch->period_start->toDateString() || $workDate > $batch->period_end->toDateString()) {
            $result->addError('Existe programacion diaria fuera del periodo del lote.');
        }

        $relationship = $assignment->employmentRelationship;
        if (! $relationship
            || $relationship->company_id !== $company->id
            || $relationship->center_id !== $batch->center_id
            || $relationship->worker?->company_id !== $company->id
            || $relationship->worker?->status !== 'active') {
            $result->addError('Existe programacion diaria para una relacion laboral ajena al centro del lote.');
        }

        foreach ($assignment->segments as $segment) {
            if ($segment->company_id !== $company->id || $segment->daily_schedule_assignment_id !== $assignment->id) {
                $result->addError('Existe un segmento diario que no corresponde a la empresa o al dia programado.');
            }
        }
    }

    private function validateAssignmentShape(
        ScheduleBatchPublicationValidationResult $result,
        Company $company,
        ScheduleBatch $batch,
        EmploymentRelationship $relationship,
        DailyScheduleAssignment $assignment,
    ): void {
        try {
            $this->dailyAssignmentValidator->validate(
                $company,
                $batch,
                $relationship,
                $assignment->toArray(),
                $assignment->segments->map(fn (DailyScheduleSegment $segment): array => $segment->toArray())->all(),
            );
        } catch (InvalidArgumentException $exception) {
            $result->addError($exception->getMessage());
            return;
        }

        match ($assignment->day_type) {
            'shift' => $this->validateShiftForPublication($result, $assignment),
            'rest' => $this->validateRestForPublication($result, $assignment),
            'flexible' => $this->validateFlexibleForPublication($result, $assignment),
            'on_call' => $this->validateOnCallForPublication($result, $assignment),
            default => null,
        };
    }

    private function validateShiftForPublication(ScheduleBatchPublicationValidationResult $result, DailyScheduleAssignment $assignment): void
    {
        if (! $assignment->shift_template_id) {
            $result->addError('Un dia con turno requiere plantilla.');
        }

        if (! in_array($assignment->timezone, DateTimeZone::listIdentifiers(), true)) {
            $result->addError('La zona horaria congelada no es valida.');
        }

        $segments = $assignment->segments->sortBy('segment_order')->values();
        $expectedOrder = 1;
        $hasWork = false;

        foreach ($segments as $segment) {
            if ($segment->segment_order !== $expectedOrder++) {
                $result->addError('El orden de segmentos diarios debe ser consecutivo.');
            }

            if ($segment->segment_type === 'work') {
                $hasWork = true;
            }

            if ($segment->segment_type === 'work' && $segment->timing_mode !== 'fixed') {
                $result->addError('Un segmento de trabajo siempre debe tener horario fijo.');
            }

            if ($segment->timing_mode === 'fixed') {
                $this->validateFixedSegmentForPublication($result, $assignment, $segment);
            }

            if ($segment->timing_mode === 'duration' && ($segment->duration_minutes ?? 0) <= 0) {
                $result->addError('Un segmento por duracion requiere minutos positivos.');
            }
        }

        if (! $hasWork) {
            $result->addError('Un dia con turno requiere al menos un segmento de trabajo.');
        }
    }

    private function validateFixedSegmentForPublication(
        ScheduleBatchPublicationValidationResult $result,
        DailyScheduleAssignment $assignment,
        DailyScheduleSegment $segment,
    ): void {
        if (! $segment->start_local_time || ! $segment->end_local_time || ! $segment->starts_at_utc || ! $segment->ends_at_utc) {
            $result->addError('Un segmento de turno no contiene instantes UTC completos.');
            return;
        }

        if ($segment->ends_at_utc->lessThanOrEqualTo($segment->starts_at_utc)) {
            $result->addError('El final UTC de un segmento debe ser posterior al inicio.');
        }

        if ($segment->starts_at_utc->diffInMinutes($segment->ends_at_utc) > 1440) {
            $result->addError('Un segmento fijo no puede superar 24 horas.');
        }

        $expectedStart = CarbonImmutable::parse($assignment->work_date->toDateString(), $assignment->timezone)
            ->addDays($segment->start_day_offset)
            ->setTimeFromTimeString($segment->start_local_time)
            ->utc();
        $expectedEnd = CarbonImmutable::parse($assignment->work_date->toDateString(), $assignment->timezone)
            ->addDays($segment->end_day_offset)
            ->setTimeFromTimeString($segment->end_local_time)
            ->utc();

        if ($segment->starts_at_utc->toDateTimeString() !== $expectedStart->toDateTimeString()
            || $segment->ends_at_utc->toDateTimeString() !== $expectedEnd->toDateTimeString()) {
            $result->addError('Los instantes UTC del segmento no coinciden con la hora local congelada.');
        }
    }

    private function validateRestForPublication(ScheduleBatchPublicationValidationResult $result, DailyScheduleAssignment $assignment): void
    {
        if ($assignment->shift_template_id
            || $assignment->segments->isNotEmpty()
            || $assignment->required_minutes !== null
            || $assignment->window_start_local_time !== null
            || $assignment->window_end_local_time !== null
            || $assignment->availability_start_local_time !== null
            || $assignment->availability_end_local_time !== null
            || $assignment->max_work_minutes !== null) {
            $result->addError('Un descanso publicado no debe contener turno, segmentos ni ventanas operativas.');
        }
    }

    private function validateFlexibleForPublication(ScheduleBatchPublicationValidationResult $result, DailyScheduleAssignment $assignment): void
    {
        if ($assignment->shift_template_id || $assignment->segments->isNotEmpty()) {
            $result->addError('Un dia flexible no debe contener plantilla ni segmentos.');
        }
    }

    private function validateOnCallForPublication(ScheduleBatchPublicationValidationResult $result, DailyScheduleAssignment $assignment): void
    {
        if ($assignment->shift_template_id || $assignment->segments->isNotEmpty() || $assignment->required_minutes !== null) {
            $result->addError('Un dia bajo demanda no debe contener plantilla, segmentos ni minutos requeridos.');
        }
    }

    private function validatePublishedConflicts(ScheduleBatchPublicationValidationResult $result, ScheduleBatch $batch): void
    {
        $conflicts = 0;
        foreach ($batch->dailyAssignments as $assignment) {
            $conflicts += DailyScheduleAssignment::query()
                ->where('employment_relationship_id', $assignment->employment_relationship_id)
                ->whereDate('work_date', $assignment->work_date->toDateString())
                ->where('schedule_batch_id', '!=', $batch->id)
                ->whereHas('scheduleBatch', fn ($query) => $query->where('status', 'published'))
                ->count();
        }

        $result->conflictingAssignments = $conflicts;
        if ($conflicts > 0) {
            $result->addError('Ya existe programacion publicada para una persona y fecha.');
        }
    }

    private function key(int $relationshipId, string $workDate): string
    {
        return $relationshipId.'|'.$workDate;
    }
}
