<?php

namespace App\Domains\Scheduling\Actions;

use App\Models\Center;
use App\Models\Company;
use App\Models\ScheduleBatch;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class ValidateScheduleBatchAction
{
    public const STATUSES = ['draft', 'published', 'superseded', 'cancelled'];
    public const CREATION_SOURCES = ['manual', 'profile', 'csv', 'api', 'mixed', 'system'];

    public function validatePeriod(string $periodStart, string $periodEnd): array
    {
        $start = CarbonImmutable::parse($periodStart)->toDateString();
        $end = CarbonImmutable::parse($periodEnd)->toDateString();

        if ($end < $start) {
            throw new InvalidArgumentException('El periodo del lote no es valido.');
        }

        return [$start, $end];
    }

    public function naturalWeekForDate(string $date): array
    {
        $start = CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY);

        return [
            $start->toDateString(),
            $start->addDays(6)->toDateString(),
        ];
    }

    public function validateCenter(Company $company, Center $center): void
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('La programacion diaria requiere una empresa activa.');
        }

        if ($center->company_id !== $company->id || $center->status !== 'active') {
            throw new InvalidArgumentException('El centro no pertenece a la empresa activa.');
        }
    }

    public function validateCreationSource(string $source): string
    {
        if (! in_array($source, self::CREATION_SOURCES, true)) {
            throw new InvalidArgumentException('La fuente de creacion del lote no es valida.');
        }

        return $source;
    }

    public function ensureNoOpenDraft(Company $company, Center $center, string $periodStart, string $periodEnd): void
    {
        $exists = ScheduleBatch::query()
            ->where('company_id', $company->id)
            ->where('center_id', $center->id)
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->where('status', 'draft')
            ->whereNull('previous_batch_id')
            ->lockForUpdate()
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('Ya existe un borrador abierto para este centro y periodo.');
        }
    }

    public function assertDraft(ScheduleBatch $batch): void
    {
        if ($batch->status !== 'draft') {
            throw new InvalidArgumentException('Solo se puede modificar un lote en borrador.');
        }
    }

    public function assertImmutable(ScheduleBatch $batch): void
    {
        if (in_array($batch->status, ['published', 'superseded', 'cancelled'], true)) {
            throw new InvalidArgumentException('El lote no permite modificaciones destructivas.');
        }
    }
}
