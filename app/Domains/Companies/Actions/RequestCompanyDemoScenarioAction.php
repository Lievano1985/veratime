<?php

namespace App\Domains\Companies\Actions;

use App\Domains\Companies\Jobs\GenerateCompanyDemoScenarioJob;
use App\Models\Company;
use App\Models\CompanyDemoScenario;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RequestCompanyDemoScenarioAction
{
    public function handle(Company $company, User $requestedBy): CompanyDemoScenario
    {
        if ($company->status !== 'active') {
            throw new InvalidArgumentException('El escenario demo requiere una empresa activa.');
        }

        if (! $requestedBy->isSuperAdmin()
            && (! $requestedBy->belongsToCompany($company)
                || ! in_array($requestedBy->roleKeyForCompany($company), RoleKey::companyManagers(), true))) {
            throw new InvalidArgumentException('El usuario no puede preparar un escenario demo para esta empresa.');
        }

        [$scenario, $shouldDispatch] = DB::transaction(function () use ($company, $requestedBy): array {
            $scenario = CompanyDemoScenario::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->first();

            if ($scenario && in_array($scenario->status, [CompanyDemoScenario::STATUS_PENDING, CompanyDemoScenario::STATUS_PROCESSING, CompanyDemoScenario::STATUS_COMPLETED], true)) {
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

        if ($shouldDispatch) {
            GenerateCompanyDemoScenarioJob::dispatch($scenario->id)->afterCommit();
        }

        return $scenario;
    }
}
