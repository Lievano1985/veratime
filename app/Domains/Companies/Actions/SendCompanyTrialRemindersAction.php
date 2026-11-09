<?php

namespace App\Domains\Companies\Actions;

use App\Domains\Companies\Support\CompanyTrialStatus;
use App\Domains\Integrations\Exceptions\BrevoDeliveryException;
use App\Domains\Integrations\Services\BrevoTransactionalEmailService;
use App\Models\Company;
use App\Models\Role;
use App\Support\RoleKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendCompanyTrialRemindersAction
{
    public function handle(): array
    {
        $summary = ['sent' => 0, 'failed' => 0, 'enabled' => false];
        $email = app(BrevoTransactionalEmailService::class);
        if (! $email->isEnabled()) {
            return $summary;
        }
        $summary['enabled'] = true;
        $roleId = Role::query()->where('key', RoleKey::ADMIN_EMPRESA)->value('id');
        if (! $roleId) {
            return $summary;
        }
        Company::query()->where('is_trial', true)->where('status', 'active')
            ->where('trial_ends_at', '>', now())->where('trial_ends_at', '<=', now()->addDays(3))
            ->orderBy('id')->each(function ($candidate) use ($email, $roleId, &$summary) {
                DB::transaction(function () use ($candidate, $email, $roleId, &$summary) {
                    $company = Company::query()->lockForUpdate()->find($candidate->id);
                    if (! $company || $company->status !== 'active') {
                        return;
                    }
                    $trial = CompanyTrialStatus::forCompany($company);
                    if (! $trial || ! $trial['urgent'] || $trial['expired']) {
                        return;
                    }
                    $settings = $company->settings ?? [];
                    foreach ($company->users()->where('users.status', 'active')->wherePivot('status', 'active')->wherePivot('role_id', $roleId)->get() as $administrator) {
                        $key = hash('sha256', $administrator->id.'|'.$administrator->email.'|'.$company->trial_ends_at->toIso8601String());
                        if (isset($settings['trial_reminders'][$key])) {
                            continue;
                        }
                        try {
                            $expiry = $trial['endsAt']->format('d/m/Y H:i').' ('.$company->timezone.')';
                            $email->send(
                                recipients: [['email' => $administrator->email, 'name' => $administrator->name]],
                                subject: 'Tu prueba de Vera Time está por vencer',
                                htmlContent: view('emails.companies.trial-reminder', compact('company', 'administrator', 'trial', 'expiry'))->render(),
                                textContent: "Hola {$administrator->name}. La prueba de {$company->name} vence el {$expiry}. Quedan {$trial['days']} días. Contacta a Vera Time para activar tu empresa. Los datos se conservan al suspenderse el acceso.",
                                tags: ['company-trial-expiring'],
                            );
                            $settings['trial_reminders'][$key] = now()->toIso8601String();
                            $company->forceFill(['settings' => $settings])->save();
                            $summary['sent']++;
                        } catch (BrevoDeliveryException $exception) {
                            $summary['failed']++;
                            Log::warning('Company trial reminder delivery failed.', ['company_id' => $company->id, 'user_id' => $administrator->id, 'status_code' => $exception->statusCode]);
                        }
                    }
                });
            });

        return $summary;
    }
}
