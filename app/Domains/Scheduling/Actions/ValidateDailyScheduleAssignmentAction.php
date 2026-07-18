<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Support\LocalTimeWindow;
use App\Models\Company;
use App\Models\DailyScheduleAssignment;
use App\Models\EmploymentRelationship;
use App\Models\OrganizationalUnit;
use App\Models\ScheduleBatch;
use App\Models\ShiftTemplate;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class ValidateDailyScheduleAssignmentAction
{
    public const DAY_TYPES = ['shift', 'rest', 'flexible', 'on_call', 'unassigned'];
    public const SOURCE_TYPES = ['profile', 'manual', 'csv', 'api', 'system'];
    public const SEGMENT_TYPES = ['work', 'break'];
    public const TIMING_MODES = ['fixed', 'duration'];

    public function validate(Company $company, ScheduleBatch $batch, EmploymentRelationship $relationship, array $data, array $segments = []): array
    {
        $this->validateContext($company, $batch, $relationship);

        $workDate = CarbonImmutable::parse((string) ($data['work_date'] ?? ''))->toDateString();
        if ($workDate < $batch->period_start->toDateString() || $workDate > $batch->period_end->toDateString()) {
            throw new InvalidArgumentException('La fecha de trabajo esta fuera del periodo del lote.');
        }

        if ($relationship->started_at->toDateString() > $workDate
            || ($relationship->ended_at && $relationship->ended_at->toDateString() < $workDate)) {
            throw new InvalidArgumentException('La relacion laboral no esta vigente para la fecha programada.');
        }

        $dayType = (string) ($data['day_type'] ?? '');
        if (! in_array($dayType, self::DAY_TYPES, true)) {
            throw new InvalidArgumentException('El tipo de dia programado no es valido.');
        }

        $sourceType = (string) ($data['source_type'] ?? 'manual');
        if (! in_array($sourceType, self::SOURCE_TYPES, true)) {
            throw new InvalidArgumentException('La fuente de la programacion diaria no es valida.');
        }

        $unit = $this->resolveUnit($company, $batch, $data['organizational_unit_id'] ?? null);
        $template = $this->resolveShiftTemplate($company, $data['shift_template_id'] ?? null);
        $payload = $this->basePayload($data, $workDate, $dayType, $sourceType, $batch->center->timezone ?: $company->timezone);

        return match ($dayType) {
            'shift' => $this->validateShift($payload, $template, $segments),
            'rest' => $this->validateRest($payload, $segments),
            'flexible' => $this->validateFlexible($payload, $segments),
            'on_call' => $this->validateOnCall($payload, $segments),
            'unassigned' => $this->validateUnassigned($payload, $segments),
        } + [
            'organizational_unit_id' => $unit?->id,
            'shift_template_id' => $template?->id,
        ];
    }

    public function validateSegments(Company $company, DailyScheduleAssignment $assignment, array $segments): array
    {
        if ($assignment->day_type !== 'shift' && $segments !== []) {
            throw new InvalidArgumentException('Solo un dia con turno puede tener segmentos.');
        }

        return $this->normalizeSegments($company, $segments);
    }

    private function validateContext(Company $company, ScheduleBatch $batch, EmploymentRelationship $relationship): void
    {
        if ($company->status !== 'active' || $batch->company_id !== $company->id || $relationship->company_id !== $company->id) {
            throw new InvalidArgumentException('La programacion diaria no corresponde a la empresa activa.');
        }

        if ($batch->center_id !== $relationship->center_id) {
            throw new InvalidArgumentException('La relacion laboral no corresponde al centro del lote.');
        }
    }

    private function resolveUnit(Company $company, ScheduleBatch $batch, mixed $unitId): ?OrganizationalUnit
    {
        if (blank($unitId)) {
            return null;
        }

        $unit = OrganizationalUnit::query()->find($unitId);
        if (! $unit || $unit->company_id !== $company->id || $unit->center_id !== $batch->center_id) {
            throw new InvalidArgumentException('La unidad organizacional no pertenece al centro del lote.');
        }

        return $unit;
    }

    private function resolveShiftTemplate(Company $company, mixed $templateId): ?ShiftTemplate
    {
        if (blank($templateId)) {
            return null;
        }

        $template = ShiftTemplate::query()->with('segments')->find($templateId);
        if (! $template || $template->company_id !== $company->id || $template->status !== 'active') {
            throw new InvalidArgumentException('La plantilla de turno no pertenece a la empresa activa.');
        }

        return $template;
    }

    private function basePayload(array $data, string $workDate, string $dayType, string $sourceType, string $defaultTimezone): array
    {
        return [
            'work_date' => $workDate,
            'day_type' => $dayType,
            'timezone' => blank($data['timezone'] ?? null) ? $defaultTimezone : trim((string) $data['timezone']),
            'source_type' => $sourceType,
            'source_reference' => $data['source_reference'] ?? null,
            'required_minutes' => isset($data['required_minutes']) ? (int) $data['required_minutes'] : null,
            'window_start_local_time' => blank($data['window_start_local_time'] ?? null) ? null : $this->normalizeTime((string) $data['window_start_local_time']),
            'window_end_local_time' => blank($data['window_end_local_time'] ?? null) ? null : $this->normalizeTime((string) $data['window_end_local_time']),
            'window_start_day_offset' => (int) ($data['window_start_day_offset'] ?? 0),
            'window_end_day_offset' => (int) ($data['window_end_day_offset'] ?? 0),
            'availability_start_local_time' => blank($data['availability_start_local_time'] ?? null) ? null : $this->normalizeTime((string) $data['availability_start_local_time']),
            'availability_end_local_time' => blank($data['availability_end_local_time'] ?? null) ? null : $this->normalizeTime((string) $data['availability_end_local_time']),
            'availability_start_day_offset' => (int) ($data['availability_start_day_offset'] ?? 0),
            'availability_end_day_offset' => (int) ($data['availability_end_day_offset'] ?? 0),
            'max_work_minutes' => isset($data['max_work_minutes']) ? (int) $data['max_work_minutes'] : null,
            'metadata' => $data['metadata'] ?? [],
        ];
    }

    private function validateShift(array $payload, ?ShiftTemplate $template, array $segments): array
    {
        if (! $template) {
            throw new InvalidArgumentException('Un dia con turno requiere plantilla.');
        }

        if ($payload['required_minutes'] !== null
            || $payload['window_start_local_time'] !== null
            || $payload['window_end_local_time'] !== null
            || $payload['availability_start_local_time'] !== null
            || $payload['availability_end_local_time'] !== null
            || $payload['max_work_minutes'] !== null) {
            throw new InvalidArgumentException('Un dia con turno no admite configuracion flexible ni guardia.');
        }

        $normalizedSegments = $this->normalizeSegments($template->company, $segments);
        if (! collect($normalizedSegments)->contains(fn (array $segment): bool => $segment['segment_type'] === 'work')) {
            throw new InvalidArgumentException('Un dia con turno requiere al menos un segmento de trabajo.');
        }

        $payload['segments'] = $normalizedSegments;

        return $payload;
    }

    private function validateRest(array $payload, array $segments): array
    {
        if ($segments !== []) {
            throw new InvalidArgumentException('Un descanso no puede tener segmentos.');
        }

        return $this->clearIncompatible($payload);
    }

    private function validateFlexible(array $payload, array $segments): array
    {
        if ($segments !== []) {
            throw new InvalidArgumentException('Un dia flexible no puede tener segmentos de turno fijo.');
        }

        if (($payload['required_minutes'] ?? 0) <= 0 || $payload['required_minutes'] > 1440) {
            throw new InvalidArgumentException('Un dia flexible requiere minutos entre 1 y 1440.');
        }

        $this->assertOptionalWindow($payload, 'window_start_local_time', 'window_end_local_time', 'window_start_day_offset', 'window_end_day_offset', 'La ventana flexible no es valida.');

        $payload['availability_start_local_time'] = null;
        $payload['availability_end_local_time'] = null;
        $payload['availability_start_day_offset'] = 0;
        $payload['availability_end_day_offset'] = 0;
        $payload['max_work_minutes'] = null;

        return $payload;
    }

    private function validateOnCall(array $payload, array $segments): array
    {
        if ($segments !== []) {
            throw new InvalidArgumentException('Un dia bajo demanda no puede tener segmentos programados.');
        }

        if (($payload['max_work_minutes'] ?? 0) <= 0 || $payload['max_work_minutes'] > 1440) {
            throw new InvalidArgumentException('Un dia bajo demanda requiere maximo de trabajo entre 1 y 1440.');
        }

        if (! $payload['availability_start_local_time'] || ! $payload['availability_end_local_time']) {
            throw new InvalidArgumentException('Un dia bajo demanda requiere disponibilidad inicial y final.');
        }

        LocalTimeWindow::assertValidWindow(
            $payload['availability_start_local_time'],
            $payload['availability_end_local_time'],
            $payload['availability_start_day_offset'],
            $payload['availability_end_day_offset'],
            'La disponibilidad bajo demanda no es valida.',
        );

        $payload['required_minutes'] = null;
        $payload['window_start_local_time'] = null;
        $payload['window_end_local_time'] = null;
        $payload['window_start_day_offset'] = 0;
        $payload['window_end_day_offset'] = 0;

        return $payload;
    }

    private function validateUnassigned(array $payload, array $segments): array
    {
        if ($segments !== []) {
            throw new InvalidArgumentException('Un dia sin definir no puede tener segmentos.');
        }

        return $this->clearIncompatible($payload);
    }

    private function clearIncompatible(array $payload): array
    {
        foreach ([
            'required_minutes',
            'window_start_local_time',
            'window_end_local_time',
            'availability_start_local_time',
            'availability_end_local_time',
            'max_work_minutes',
        ] as $field) {
            $payload[$field] = null;
        }

        foreach ([
            'window_start_day_offset',
            'window_end_day_offset',
            'availability_start_day_offset',
            'availability_end_day_offset',
        ] as $field) {
            $payload[$field] = 0;
        }

        return $payload;
    }

    private function assertOptionalWindow(array $payload, string $start, string $end, string $startOffset, string $endOffset, string $message): void
    {
        $hasStart = filled($payload[$start] ?? null);
        $hasEnd = filled($payload[$end] ?? null);

        if ($hasStart !== $hasEnd) {
            throw new InvalidArgumentException($message);
        }

        if ($hasStart) {
            LocalTimeWindow::assertValidWindow($payload[$start], $payload[$end], $payload[$startOffset], $payload[$endOffset], $message);
        }
    }

    private function normalizeSegments(Company $company, array $segments): array
    {
        $normalized = [];
        $expectedOrder = 1;
        foreach (array_values($segments) as $segment) {
            $segmentType = (string) ($segment['segment_type'] ?? '');
            $timingMode = (string) ($segment['timing_mode'] ?? '');

            if (! in_array($segmentType, self::SEGMENT_TYPES, true) || ! in_array($timingMode, self::TIMING_MODES, true)) {
                throw new InvalidArgumentException('El segmento diario no es valido.');
            }

            if ($segmentType === 'work' && $timingMode !== 'fixed') {
                throw new InvalidArgumentException('Un segmento de trabajo siempre debe tener horario fijo.');
            }

            $payload = [
                'segment_order' => $expectedOrder++,
                'segment_type' => $segmentType,
                'timing_mode' => $timingMode,
                'start_local_time' => blank($segment['start_local_time'] ?? null) ? null : $this->normalizeTime((string) $segment['start_local_time']),
                'end_local_time' => blank($segment['end_local_time'] ?? null) ? null : $this->normalizeTime((string) $segment['end_local_time']),
                'start_day_offset' => (int) ($segment['start_day_offset'] ?? 0),
                'end_day_offset' => (int) ($segment['end_day_offset'] ?? 0),
                'starts_at_utc' => $segment['starts_at_utc'] ?? null,
                'ends_at_utc' => $segment['ends_at_utc'] ?? null,
                'duration_minutes' => isset($segment['duration_minutes']) ? (int) $segment['duration_minutes'] : null,
                'is_paid' => (bool) ($segment['is_paid'] ?? false),
                'shift_template_segment_id' => $segment['shift_template_segment_id'] ?? null,
                'metadata' => $segment['metadata'] ?? [],
            ];

            if (! in_array($payload['start_day_offset'], [0, 1], true) || ! in_array($payload['end_day_offset'], [0, 1], true)) {
                throw new InvalidArgumentException('El desplazamiento de dia del segmento no es valido.');
            }

            if ($timingMode === 'fixed') {
                if (! $payload['start_local_time'] || ! $payload['end_local_time']) {
                    throw new InvalidArgumentException('Un segmento fijo requiere hora inicial y final.');
                }

                LocalTimeWindow::assertValidWindow(
                    $payload['start_local_time'],
                    $payload['end_local_time'],
                    $payload['start_day_offset'],
                    $payload['end_day_offset'],
                    'El rango del segmento diario no es valido.',
                );

                $payload['duration_minutes'] = null;
            } else {
                if ($segmentType !== 'break' || ($payload['duration_minutes'] ?? 0) <= 0) {
                    throw new InvalidArgumentException('Solo un descanso puede usar duracion positiva.');
                }

                $payload['start_local_time'] = null;
                $payload['end_local_time'] = null;
                $payload['start_day_offset'] = 0;
                $payload['end_day_offset'] = 0;
                $payload['starts_at_utc'] = null;
                $payload['ends_at_utc'] = null;
            }

            if ($payload['shift_template_segment_id']) {
                $templateSegment = \App\Models\ShiftTemplateSegment::query()->find($payload['shift_template_segment_id']);
                if (! $templateSegment || $templateSegment->company_id !== $company->id) {
                    throw new InvalidArgumentException('El segmento de plantilla no pertenece a la empresa activa.');
                }
            }

            $normalized[] = $payload;
        }

        return $normalized;
    }

    private function normalizeTime(string $time): string
    {
        LocalTimeWindow::timeToMinutes($time);

        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
