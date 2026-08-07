<?php

namespace App\Domains\WorkDays\Actions;

use App\Domains\Alerts\Actions\EvaluateWorkDayAlertsAction;
use App\Domains\LegalRules\Actions\ApplyOrdinaryOvertimeForDateRangeAction;
use App\Domains\LegalRules\Actions\ApplySpecialLegalCasesForDateRangeAction;
use App\Domains\LegalRules\Actions\ClassifyWorkDayCalculationsForDateRangeAction;
use App\Models\Company;
use App\Models\EmploymentRelationship;
use App\Models\User;
use App\Models\WorkDay;
use App\Models\WorkDayCalculation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class ProcessSingleWorkDayAction
{
    public function __construct(
        private readonly RefreshWorkDaysForDateRangeAction $refresh,
        private readonly ResolveWorkDayForRelationshipDateAction $resolveWorkDay,
        private readonly CalculateWorkDayAction $calculate,
        private readonly ClassifyWorkDayCalculationsForDateRangeAction $classify,
        private readonly ApplyOrdinaryOvertimeForDateRangeAction $ordinaryOvertime,
        private readonly ApplySpecialLegalCasesForDateRangeAction $specialLegalCases,
        private readonly EvaluateWorkDayAlertsAction $alerts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        Company $company,
        EmploymentRelationship $relationship,
        string|CarbonInterface $workDate,
        ?User $actor = null,
        string $generatedByType = WorkDayCalculation::GENERATED_BY_SYSTEM,
        ?string $reason = null,
        string $mode = 'single',
    ): array {
        $this->assertValidScope($company, $relationship);

        $date = $workDate instanceof CarbonInterface
            ? $workDate->toDateString()
            : CarbonImmutable::parse($workDate)->toDateString();
        $weekStart = CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY)->toDateString();
        $weekEnd = CarbonImmutable::parse($date)->endOfWeek(CarbonInterface::SUNDAY)->toDateString();

        $refreshResult = $this->refresh->handle($company, $date, $date, $relationship->center);
        $workDay = $this->resolveWorkDay->handle($company, $relationship, $date);

        if (! $workDay instanceof WorkDay) {
            return $this->summary(
                company: $company,
                relationship: $relationship,
                date: $date,
                mode: $mode,
                status: 'skipped',
                refreshResult: $refreshResult,
                reason: 'No existe jornada generable para la relacion y fecha.',
            );
        }

        $calculation = $this->calculate->handle(
            $company,
            $workDay,
            $actor,
            $generatedByType,
            $reason ?: 'Proceso puntual de jornada',
        );

        $classificationResult = ['total' => 0, 'classified' => 0, 'pending' => 0];
        $ordinaryOvertimeResult = ['total' => 0, 'calculated' => 0, 'pending' => 0];
        $specialLegalCasesResult = ['total' => 0, 'calculated' => 0, 'pending' => 0, 'sunday' => 0, 'mandatory_rest' => 0, 'weekly_rest_review' => 0];
        $alertsResult = ['created_or_updated' => 0, 'closed' => 0, 'open' => 0];

        if ($calculation instanceof WorkDayCalculation) {
            $classificationResult = $this->classify->handle($company, $date, $date, $relationship->center);
            $ordinaryOvertimeResult = $this->ordinaryOvertime->handle($company, $date, $date, $relationship->center);
            $specialLegalCasesResult = $this->specialLegalCases->handle($company, $weekStart, $weekEnd, $relationship->center);
            $alertsResult = $this->alerts->handle($company, $workDay->refresh());
        }

        $workDay->refresh();

        return $this->summary(
            company: $company,
            relationship: $relationship,
            date: $date,
            mode: $mode,
            status: $calculation instanceof WorkDayCalculation ? $workDay->status : 'skipped',
            refreshResult: $refreshResult,
            workDay: $workDay,
            calculation: $calculation,
            classificationResult: $classificationResult,
            ordinaryOvertimeResult: $ordinaryOvertimeResult,
            specialLegalCasesResult: $specialLegalCasesResult,
            alertsResult: $alertsResult,
        );
    }

    private function assertValidScope(Company $company, EmploymentRelationship $relationship): void
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('El proceso puntual de jornada requiere una empresa activa.');
        }

        if ($relationship->company_id !== $company->id) {
            throw new InvalidArgumentException('La relacion laboral debe pertenecer a la empresa activa.');
        }

        if ($relationship->center && $relationship->center->company_id !== $company->id) {
            throw new InvalidArgumentException('El centro de la relacion laboral debe pertenecer a la empresa activa.');
        }
    }

    /**
     * @param array<string, mixed> $refreshResult
     * @param array<string, mixed> $classificationResult
     * @param array<string, mixed> $ordinaryOvertimeResult
     * @param array<string, mixed> $specialLegalCasesResult
     * @param array<string, mixed> $alertsResult
     * @return array<string, mixed>
     */
    private function summary(
        Company $company,
        EmploymentRelationship $relationship,
        string $date,
        string $mode,
        string $status,
        array $refreshResult,
        ?WorkDay $workDay = null,
        ?WorkDayCalculation $calculation = null,
        array $classificationResult = [],
        array $ordinaryOvertimeResult = [],
        array $specialLegalCasesResult = [],
        array $alertsResult = [],
        ?string $reason = null,
    ): array {
        return [
            'company_id' => $company->id,
            'employment_relationship_id' => $relationship->id,
            'worker_id' => $relationship->worker_id,
            'center_id' => $relationship->center_id,
            'work_date' => $date,
            'mode' => $mode,
            'status' => $status,
            'work_day_id' => $workDay?->id,
            'work_day_calculation_id' => $calculation?->id,
            'scheduled' => $refreshResult['scheduled'] ?? 0,
            'unscheduled' => $refreshResult['unscheduled'] ?? 0,
            'calculated' => $calculation instanceof WorkDayCalculation ? 1 : 0,
            'classified' => $classificationResult['classified'] ?? 0,
            'ordinary_overtime' => $ordinaryOvertimeResult['calculated'] ?? 0,
            'special_legal_cases' => $specialLegalCasesResult['calculated'] ?? 0,
            'alerts_created_or_updated' => $alertsResult['created_or_updated'] ?? 0,
            'alerts_closed' => $alertsResult['closed'] ?? 0,
            'alerts_open' => $alertsResult['open'] ?? 0,
            'reason' => $reason,
        ];
    }
}
