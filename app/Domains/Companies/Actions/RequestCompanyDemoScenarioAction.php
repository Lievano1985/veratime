<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use App\Models\CompanyDemoScenario;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class RequestCompanyDemoScenarioAction
{
    public function __construct(
        private readonly AssessCompanyDemoScenarioEligibilityAction $eligibility,
        private readonly GenerateCompanyDemoScenarioAction $generateScenario,
    ) {}

    public function handle(Company $company, User $requestedBy): CompanyDemoScenario
    {
        if (! $requestedBy->isSuperAdmin()
            && (! $requestedBy->belongsToCompany($company)
                || ! in_array($requestedBy->roleKeyForCompany($company), RoleKey::companyManagers(), true))) {
            throw new InvalidArgumentException('El usuario no puede preparar un escenario demo para esta empresa.');
        }

        $eligibility = $this->eligibility->handle($company);
        if (! $eligibility['allowed']) {
            throw new InvalidArgumentException((string) $eligibility['reason']);
        }

        [$scenario, $shouldGenerate] = DB::transaction(function () use ($company, $requestedBy): array {
            $scenario = CompanyDemoScenario::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->first();

            if ($scenario && in_array($scenario->status, [CompanyDemoScenario::STATUS_PROCESSING, CompanyDemoScenario::STATUS_COMPLETED], true)) {
                return [$scenario, false];
            }

            $scenario = CompanyDemoScenario::query()->updateOrCreate(
                ['company_id' => $company->id],
                [
                    'requested_by_user_id' => $requestedBy->id,
                    'status' => CompanyDemoScenario::STATUS_PENDING,
                    'period_start' => null,
                    'period_end' => null,
                    'summary' => null,
                    'error_message' => null,
                    'started_at' => null,
                    'completed_at' => null,
                ],
            );

            return [$scenario, true];
        });

        if (! $shouldGenerate) {
            return $scenario;
        }

        try {
            return $this->generateScenario->handle($scenario);
        } catch (Throwable $exception) {
            CompanyDemoScenario::query()
                ->whereKey($scenario->id)
                ->where('status', '!=', CompanyDemoScenario::STATUS_COMPLETED)
                ->update([
                    'status' => CompanyDemoScenario::STATUS_FAILED,
                    'error_message' => mb_substr($exception->getMessage(), 0, 5000),
                    'completed_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::error('Company demo scenario generation failed.', [
                'scenario_id' => $scenario->id,
                'company_id' => $company->id,
                'exception' => $exception,
            ]);

            return $scenario->fresh() ?? $scenario;
        }
    }
}
