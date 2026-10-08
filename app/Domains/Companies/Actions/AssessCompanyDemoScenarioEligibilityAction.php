<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use App\Models\CompanyDemoScenario;

class AssessCompanyDemoScenarioEligibilityAction
{
    /**
     * @return array{allowed: bool, reason: ?string, scenario: ?CompanyDemoScenario}
     */
    public function handle(Company $company): array
    {
        $scenario = CompanyDemoScenario::query()
            ->where('company_id', $company->id)
            ->latest('id')
            ->first();

        if ($company->status !== 'active') {
            return $this->denied('El escenario demo requiere una empresa activa.', $scenario);
        }

        if ($scenario && in_array($scenario->status, [
            CompanyDemoScenario::STATUS_PENDING,
            CompanyDemoScenario::STATUS_PROCESSING,
        ], true)) {
            return $this->denied('El escenario demo ya se esta preparando.', $scenario);
        }

        if ($scenario?->status === CompanyDemoScenario::STATUS_COMPLETED) {
            return $this->denied('Esta empresa ya tiene un escenario demo completado.', $scenario);
        }

        if ($this->hasOperationalData($company)) {
            return $this->denied('El demo solo puede generarse en una empresa sin datos operativos.', $scenario);
        }

        return ['allowed' => true, 'reason' => null, 'scenario' => $scenario];
    }

    private function hasOperationalData(Company $company): bool
    {
        return $company->centers()->exists()
            || $company->workers()->exists()
            || $company->employmentRelationships()->exists()
            || $company->shiftTemplates()->exists()
            || $company->scheduleProfiles()->exists()
            || $company->scheduleBatches()->exists()
            || $company->timeEvents()->exists()
            || $company->attendanceIncidents()->exists()
            || $company->attendancePeriods()->exists();
    }

    /** @return array{allowed: false, reason: string, scenario: ?CompanyDemoScenario} */
    private function denied(string $reason, ?CompanyDemoScenario $scenario): array
    {
        return ['allowed' => false, 'reason' => $reason, 'scenario' => $scenario];
    }
}
