<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Data\DailyScheduleCsvRowValidationResult;
use App\Domains\Scheduling\Support\DailyScheduleCsvSchema;
use App\Models\EmploymentRelationship;
use App\Models\ImportBatch;
use App\Models\ScheduleBatch;
use App\Models\Worker;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ResolveDailyScheduleCsvRowAction
{
    public function __construct(
        private BuildDailyScheduleCsvAssignmentPayloadAction $payloadBuilder,
        private BuildDailyScheduleCsvPreviewFingerprintAction $fingerprints,
    ) {
    }

    /**
     * @param array<string, string> $row
     */
    public function handle(ImportBatch $importBatch, ScheduleBatch $batch, int $rowNumber, array $row): DailyScheduleCsvRowValidationResult
    {
        $errors = [];
        $warnings = [];

        try {
            $this->validateBasicFields($batch, $row);
            $relationship = $this->resolveRelationship($importBatch, $batch, $row['clave_empleado'], $row['fecha']);
            $built = $this->payloadBuilder->handle($importBatch, $batch, $relationship, $row);
            $normalized = $built['normalized'];
            $normalized['assignment']['source_reference']['row_number'] = $rowNumber;
            $warnings = $built['warnings'];
            $fingerprint = $this->fingerprints->rowFingerprint($normalized);
            $status = $warnings === [] ? 'valid' : 'warning';

            return new DailyScheduleCsvRowValidationResult(
                rowNumber: $rowNumber,
                status: $status,
                rawData: $row,
                normalizedData: $normalized,
                warnings: $warnings,
                relationship: $relationship,
                existingAssignment: $built['existing_assignment'],
                rowFingerprint: $fingerprint,
            );
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        }

        return new DailyScheduleCsvRowValidationResult(
            rowNumber: $rowNumber,
            status: 'invalid',
            rawData: $row,
            errors: $errors,
        );
    }

    /**
     * @param array<string, string> $row
     */
    private function validateBasicFields(ScheduleBatch $batch, array $row): void
    {
        if (blank($row['clave_empleado'] ?? null)) {
            throw new InvalidArgumentException('La clave de empleado es obligatoria.');
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['fecha'] ?? '')) {
            throw new InvalidArgumentException('La fecha debe usar formato YYYY-MM-DD.');
        }

        $date = CarbonImmutable::createFromFormat('Y-m-d', $row['fecha']);
        if (! $date || $date->format('Y-m-d') !== $row['fecha']) {
            throw new InvalidArgumentException('La fecha no es valida.');
        }

        if ($row['fecha'] < $batch->period_start->toDateString() || $row['fecha'] > $batch->period_end->toDateString()) {
            throw new InvalidArgumentException('La fecha esta fuera del periodo del lote.');
        }

        if (! array_key_exists($row['tipo_dia'] ?? '', DailyScheduleCsvSchema::DAY_TYPE_MAP)) {
            throw new InvalidArgumentException('El tipo de dia no es valido.');
        }
    }

    private function resolveRelationship(ImportBatch $importBatch, ScheduleBatch $batch, string $employeeCode, string $date): EmploymentRelationship
    {
        $worker = Worker::query()
            ->where('company_id', $importBatch->company_id)
            ->where('status', 'active')
            ->whereRaw('UPPER(employee_code) = ?', [mb_strtoupper(trim($employeeCode))])
            ->first();

        if (! $worker) {
            throw new InvalidArgumentException('No existe un trabajador activo con esa clave en la empresa.');
        }

        $relationships = EmploymentRelationship::query()
            ->where('company_id', $importBatch->company_id)
            ->where('worker_id', $worker->id)
            ->where('center_id', $batch->center_id)
            ->where('status', 'active')
            ->whereDate('started_at', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('ended_at')
                    ->orWhereDate('ended_at', '>=', $date);
            })
            ->get();

        if ($relationships->count() !== 1) {
            throw new InvalidArgumentException('No se encontro una relacion laboral vigente y unica para el centro del lote.');
        }

        return $relationships->first();
    }
}
