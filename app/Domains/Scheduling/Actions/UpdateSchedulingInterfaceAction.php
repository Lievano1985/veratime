<?php

namespace App\Domains\Scheduling\Actions;

use App\Domains\Scheduling\Support\SchedulingInterface;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdateSchedulingInterfaceAction
{
    public function handle(User $actor, Company $company, string $mode): CompanySetting
    {
        Gate::forUser($actor)->authorize('configureSchedulingInterface', $company);
        Validator::make(['schedulingInterface' => $mode], ['schedulingInterface' => 'required|in:planner,classic'])->validate();

        return DB::transaction(function () use ($actor, $company, $mode) {
            $company = Company::query()->lockForUpdate()->findOrFail($company->id);
            Gate::forUser($actor)->authorize('configureSchedulingInterface', $company);
            $setting = $company->setting()->firstOrCreate(['company_id' => $company->id], Company::defaultSettings());
            $metadata = $setting->metadata ?? [];
            $previous = $metadata['scheduling_interface'] ?? SchedulingInterface::PLANNER;
            if ($previous !== $mode) {
                $metadata['scheduling_interface_changed_by'] = $actor->id;
                $metadata['scheduling_interface_changed_at'] = now()->toIso8601String();
            }
            $metadata['scheduling_interface'] = $mode;
            $setting->forceFill(['metadata' => $metadata])->save();
            $company->unsetRelation('setting');

            return $setting;
        });
    }
}
