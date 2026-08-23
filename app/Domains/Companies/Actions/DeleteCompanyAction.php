<?php

namespace App\Domains\Companies\Actions;

use App\Models\Company;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DeleteCompanyAction
{
    public function handle(User $actor, Company $company): void
    {
        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Solo el super admin puede eliminar empresas.');
        }

        DB::transaction(function () use ($company): void {
            $lockedCompany = Company::query()
                ->whereKey($company->id)
                ->lockForUpdate()
                ->firstOrFail();

            $customerAccountId = $lockedCompany->customer_account_id;

            Schema::disableForeignKeyConstraints();

            try {
                foreach ($this->companyScopedTables() as $table) {
                    DB::table($table)->where('company_id', $lockedCompany->id)->delete();
                }

                DB::table('companies')->where('id', $lockedCompany->id)->delete();
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            if ($customerAccountId && ! Company::query()->where('customer_account_id', $customerAccountId)->exists()) {
                DB::table('customer_accounts')->where('id', $customerAccountId)->delete();
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function companyScopedTables(): array
    {
        return collect([
            'attendance_incidents',
            'attendance_period_scopes',
            'attendance_periods',
            'alerts',
            'work_day_calculations',
            'work_days',
            'daily_schedule_segments',
            'daily_schedule_assignments',
            'import_rows',
            'import_batches',
            'schedule_batches',
            'schedule_profile_assignments',
            'schedule_profile_weekly_rules',
            'schedule_profile_cycle_rules',
            'schedule_profile_flexible_rules',
            'schedule_profile_on_call_rules',
            'schedule_profiles',
            'shift_template_segments',
            'shift_templates',
            'schedule_assignments',
            'schedule_breaks',
            'schedule_days',
            'schedules',
            'time_events',
            'worker_credentials',
            'employment_unit_assignments',
            'operational_scope_assignments',
            'organizational_units',
            'labor_conditions',
            'employment_relationships',
            'workers',
            'mandatory_rest_days',
            'legal_parameters',
            'legal_rules',
            'company_settings',
            'centers',
            'company_user',
        ])
            ->filter(fn (string $table): bool => Schema::hasTable($table) && Schema::hasColumn($table, 'company_id'))
            ->values()
            ->all();
    }
}
