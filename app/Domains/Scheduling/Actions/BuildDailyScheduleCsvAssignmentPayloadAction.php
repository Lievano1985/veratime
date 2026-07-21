<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Support\DailyScheduleCsvSchema;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\ImportBatch;
use App\Models\ScheduleBatch;
use App\Models\ShiftTemplate;
use InvalidArgumentException;

class BuildDailyScheduleCsvAssignmentPayloadAction
{
    public function __construct(
        private BuildDailyScheduleSegmentsFromShiftTemplateAction $segmentsFromTemplate,
        private BuildDailyScheduleCsvPreviewFingerprintAction $fingerprints,
        private ValidateDailyScheduleAssignmentAction $assignmentValidator,
    ) {
    }

    /**
     * @param array<string, string> $row
     * @return array{normalized: array<string, mixed>, warnings: list<string>, existing_assignment: DailyScheduleAssignment|null}
     */
    public function handle(ImportBatch $importBatch, ScheduleBatch $batch, EmploymentRelationship $relationship, array $row): array
    {
        $dayType = DailyScheduleCsvSchema::DAY_TYPE_MAP[$row['tipo_dia'] ?? ''] ?? null;
        if (! $dayType) {
            throw new InvalidArgumentException('El tipo de dia no es valido.');
        }

        $workDate = $row['fecha'];
        $existing = DailyScheduleAssignment::query()
            ->with('segments')
            ->where('company_id', $importBatch->company_id)
            ->where('schedule_batch_id', $batch->id)
            ->where('employment_relationship_id', $relationship->id)
            ->whereDate('work_date', $workDate)
            ->first();

        if ($batch->previous_batch_id !== null && ! $existing) {
            throw new InvalidArgumentException('Una correccion solo puede modificar dias ya incluidos en la version publicada.');
        }

        $assignment = [
            'work_date' => $workDate,
            'day_type' => $dayType,
            'timezone' => $batch->center->timezone ?: $batch->company->timezone,
            'source_type' => 'csv',
            'source_reference' => [
                'schema_version' => 1,
                'source' => 'daily_schedule_csv',
                'import_batch_id' => $importBatch->id,
                'row_number' => null,
                'file_sha256' => $importBatch->file_sha256,
                'reason' => $importBatch->reason,
                'row_reason' => blank($row['motivo'] ?? null) ? null : $row['motivo'],
                'previous_source_type' => $existing?->source_type,
            ],
            'organizational_unit_id' => $existing?->organizational_unit_id,
            'shift_template_id' => null,
            'required_minutes' => null,
            'window_start_local_time' => null,
            'window_end_local_time' => null,
            'window_start_day_offset' => 0,
            'window_end_day_offset' => 0,
            'availability_start_local_time' => null,
            'availability_end_local_time' => null,
            'availability_start_day_offset' => 0,
            'availability_end_day_offset' => 0,
            'max_work_minutes' => null,
            'metadata' => ['import_type' => 'daily_schedule_csv'],
        ];
        $segments = [];

        if ($dayType === 'shift') {
            $template = $this->resolveShiftTemplate($importBatch, $row['codigo_turno'] ?? '');
            $assignment['shift_template_id'] = $template->id;
            $segments = $this->segmentsFromTemplate->handle($template, $workDate, $assignment['timezone']);
        } elseif ($dayType === 'flexible') {
            $assignment['required_minutes'] = $this->positiveInteger($row['minutos_requeridos'] ?? '', 'Un dia flexible requiere minutos.');
            $assignment['window_start_local_time'] = $this->optionalTime($row['inicio_ventana'] ?? '');
            $assignment['window_end_local_time'] = $this->optionalTime($row['fin_ventana'] ?? '');
            $assignment['window_start_day_offset'] = $this->optionalOffset($row['offset_inicio_ventana'] ?? '0');
            $assignment['window_end_day_offset'] = $this->optionalOffset($row['offset_fin_ventana'] ?? '0');
        } elseif ($dayType === 'on_call') {
            $assignment['availability_start_local_time'] = $this->requiredTime($row['inicio_disponibilidad'] ?? '', 'Una guardia requiere inicio de disponibilidad.');
            $assignment['availability_end_local_time'] = $this->requiredTime($row['fin_disponibilidad'] ?? '', 'Una guardia requiere fin de disponibilidad.');
            $assignment['availability_start_day_offset'] = $this->optionalOffset($row['offset_inicio_disponibilidad'] ?? '0');
            $assignment['availability_end_day_offset'] = $this->optionalOffset($row['offset_fin_disponibilidad'] ?? '0');
            $assignment['max_work_minutes'] = $this->positiveInteger($row['maximo_minutos_trabajo'] ?? '', 'Una guardia requiere maximo de trabajo.');
        }

        $this->assignmentValidator->validate($batch->company, $batch, $relationship, $assignment, $segments);

        $normalized = [
            'employment_relationship_id' => $relationship->id,
            'work_date' => $workDate,
            'assignment' => $assignment,
            'segments' => $segments,
        ];
        $warnings = [];

        if ($this->fingerprints->rowFingerprint($normalized) === $this->fingerprints->assignmentFingerprint($existing)) {
            $warnings[] = 'La fila no cambia funcionalmente la programacion existente.';
        }

        if ($existing && $importBatch->existing_assignment_policy === 'preserve_existing') {
            $warnings[] = 'La programacion existente sera preservada por la politica del import.';
        }

        return [
            'normalized' => $normalized,
            'warnings' => $warnings,
            'existing_assignment' => $existing,
        ];
    }

    private function resolveShiftTemplate(ImportBatch $importBatch, string $code): ShiftTemplate
    {
        if (blank($code)) {
            throw new InvalidArgumentException('Un dia con turno requiere codigo de turno.');
        }

        $template = ShiftTemplate::query()
            ->where('company_id', $importBatch->company_id)
            ->where('status', 'active')
            ->whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))])
            ->first();

        if (! $template) {
            throw new InvalidArgumentException('El codigo de turno no existe o esta inactivo.');
        }

        return $template;
    }

    private function positiveInteger(string $value, string $message): int
    {
        if (! preg_match('/^[1-9][0-9]*$/', trim($value))) {
            throw new InvalidArgumentException($message);
        }

        return (int) $value;
    }

    private function optionalTime(string $value): ?string
    {
        return blank($value) ? null : $this->requiredTime($value, 'La hora no tiene formato HH:MM.');
    }

    private function requiredTime(string $value, string $message): string
    {
        $value = trim($value);
        if (! preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value)) {
            throw new InvalidArgumentException($message);
        }

        return $value.':00';
    }

    private function optionalOffset(string $value): int
    {
        $value = blank($value) ? '0' : trim($value);
        if (! in_array($value, ['0', '1'], true)) {
            throw new InvalidArgumentException('El desplazamiento de dia debe ser 0 o 1.');
        }

        return (int) $value;
    }
}
