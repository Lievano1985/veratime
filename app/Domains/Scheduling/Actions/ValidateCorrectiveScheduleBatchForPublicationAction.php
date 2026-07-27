<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\CorrectiveScheduleBatchPublicationValidationResult;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\ScheduleBatch;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ValidateCorrectiveScheduleBatchForPublicationAction
{
    public function __construct(
        private CompareScheduleBatchVersionsAction $compareVersions,
        private VerifyPublishedScheduleBatchSnapshotAction $verifySnapshot,
        private ValidateDailyScheduleAssignmentAction $assignmentValidator,
    ) {
    }

    public function handle(User $actor, Company $company, ScheduleBatch $corrective): CorrectiveScheduleBatchPublicationValidationResult
    {
        $corrective = ScheduleBatch::query()
            ->with(['company', 'center', 'previousBatch', 'dailyAssignments.segments', 'dailyAssignments.employmentRelationship.worker'])
            ->findOrFail($corrective->id);

        $result = new CorrectiveScheduleBatchPublicationValidationResult($corrective, $corrective->previousBatch);

        if ($company->status !== 'active') {
            $result->addError('La empresa debe estar activa para publicar la correccion.');
        }

        if (! Gate::forUser($actor)->allows('publishCorrection', $corrective)) {
            $result->addError('El usuario no puede publicar correcciones de programacion.');
        }

        if ($corrective->status !== 'draft') {
            $result->addError('Solo una correccion en borrador puede publicarse.');
        }

        if (! $corrective->previous_batch_id) {
            $result->addError('La correccion debe tener una version anterior.');
        }

        if (blank($corrective->correction_reason)) {
            $result->addError('El motivo general de correccion es obligatorio.');
        }

        $previous = $corrective->previousBatch;
        if (! $previous) {
            $result->addError('No se encontro la version anterior.');
            return $result;
        }

        if ($previous->company_id !== $company->id
            || $corrective->company_id !== $company->id
            || $previous->center_id !== $corrective->center_id
            || $previous->period_start->toDateString() !== $corrective->period_start->toDateString()
            || $previous->period_end->toDateString() !== $corrective->period_end->toDateString()) {
            $result->addError('La correccion no corresponde a la misma empresa, centro y periodo.');
        }

        if ($previous->status !== 'published') {
            $result->addError('La version anterior ya fue sustituida.');
        }

        $snapshot = $this->verifySnapshot->handle($company, $previous);
        if (! $snapshot->valid) {
            $result->addError('La version anterior no tiene snapshot integro.');
        }

        try {
            $comparison = $this->compareVersions->handle($previous, $corrective);
            $result->assignmentsExpected = $previous->dailyAssignments()->count();
            $result->assignmentsFound = $corrective->dailyAssignments()->count();
            $result->assignmentsMissing = $comparison->removedDays;
            $result->assignmentsAdded = $comparison->addedDays;
            $result->changedDays = $comparison->changedDays;
            $result->unchangedDays = $comparison->unchangedDays;
        } catch (InvalidArgumentException $exception) {
            $result->addError($exception->getMessage());
            return $result;
        }

        if ($result->assignmentsMissing > 0) {
            $result->addError('Falta programacion que estaba incluida en la version anterior.');
        }

        if ($result->assignmentsAdded > 0) {
            $result->addError('La correccion contiene programacion adicional fuera de la cobertura historica.');
        }

        if ($result->changedDays < 1) {
            $result->addError('La correccion no contiene cambios respecto de la version publicada.');
        }

        foreach ($corrective->dailyAssignments as $assignment) {
            $result->countsByDayType[$assignment->day_type] = ($result->countsByDayType[$assignment->day_type] ?? 0) + 1;

            if ($assignment->day_type === 'unassigned') {
                $result->assignmentsUnassigned++;
                $result->addError('Existen dias pendientes de definicion.');
                continue;
            }

            $relationship = $assignment->employmentRelationship;
            if (! $relationship || $relationship->company_id !== $company->id || $relationship->center_id !== $corrective->center_id) {
                $result->addError('Existe programacion diaria para una relacion laboral ajena al lote.');
                continue;
            }

            try {
                $this->assignmentValidator->validate(
                    $company,
                    $corrective,
                    $relationship,
                    $assignment->toArray(),
                    $assignment->segments->map(fn ($segment): array => $segment->toArray())->all(),
                );
            } catch (InvalidArgumentException $exception) {
                $result->addError($exception->getMessage());
            }
        }

        $result->conflictingBatches = $this->conflictingPublishedAssignments($corrective, $previous);
        if ($result->conflictingBatches > 0) {
            $result->addError('Existe otra programacion publicada en conflicto.');
        }

        $result->snapshotReady = $result->valid()
            && $result->assignmentsExpected === $result->assignmentsFound
            && $result->assignmentsUnassigned === 0
            && $result->changedDays > 0;

        return $result;
    }

    private function conflictingPublishedAssignments(ScheduleBatch $corrective, ScheduleBatch $previous): int
    {
        $conflicts = 0;

        foreach ($corrective->dailyAssignments as $assignment) {
            $conflicts += DailyScheduleAssignment::query()
                ->where('employment_relationship_id', $assignment->employment_relationship_id)
                ->whereDate('work_date', $assignment->work_date->toDateString())
                ->whereHas('scheduleBatch', fn ($query) => $query
                    ->where('status', 'published')
                    ->where('id', '!=', $previous->id))
                ->count();
        }

        return $conflicts;
    }
}
