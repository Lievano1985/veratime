<?php

namespace App\Domains\Companies\Actions;

use App\Domains\Companies\Data\CreateTenantWithAdminResult;
use App\Domains\Integrations\Exceptions\BrevoDeliveryException;
use App\Domains\Products\Actions\EnsureCustomerAccountHasProductAction;
use App\Models\Company;
use App\Models\CustomerAccount;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateTenantWithAdminAction
{
    public function __construct(
        private readonly EnsureCustomerAccountHasProductAction $ensureCustomerAccountHasProduct,
        private readonly SendCompanyAdminWelcomeEmailAction $sendAdministratorWelcomeEmail,
    ) {}

    /**
     * @param  array{company: array<string, mixed>, admin: array<string, mixed>}  $data
     */
    public function handle(User $actor, array $data): CreateTenantWithAdminResult
    {
        if (! $actor->isSuperAdmin()) {
            throw new AuthorizationException('Solo el super administrador puede crear tenants guiados.');
        }

        [$company, $administrator, $reusedExistingAdministrator] = DB::transaction(function () use ($data): array {
            $email = Str::lower(trim((string) data_get($data, 'admin.email')));
            $existingAdministrator = User::query()->where('email', $email)->first();

            if ($existingAdministrator?->companies()->exists()) {
                throw ValidationException::withMessages([
                    'createForm.admin_email' => 'Este correo ya tiene acceso a una empresa. Usa un correo nuevo para este administrador.',
                ]);
            }

            if ($existingAdministrator && ($existingAdministrator->global_role !== null || $existingAdministrator->status !== 'active')) {
                throw ValidationException::withMessages([
                    'createForm.admin_email' => 'El usuario existente no esta disponible para asignarse como administrador de empresa.',
                ]);
            }

            $adminRole = Role::query()->where('key', RoleKey::ADMIN_EMPRESA)->first();

            if (! $adminRole) {
                throw ValidationException::withMessages([
                    'createForm.admin_email' => 'No existe el rol administrador de empresa.',
                ]);
            }

            $accountType = (string) data_get($data, 'company.account_type', 'single_company');

            if (! in_array($accountType, ['single_company', 'multi_company'], true)) {
                throw ValidationException::withMessages([
                    'createForm.account_type' => 'Selecciona un tipo de cuenta valido.',
                ]);
            }

            $customerAccount = CustomerAccount::query()->create([
                'name' => trim((string) data_get($data, 'company.name')),
                'account_type' => $accountType,
                'status' => 'active',
                'metadata' => [],
            ]);

            $this->ensureCustomerAccountHasProduct->handle($customerAccount);

            $company = Company::query()->create([
                'customer_account_id' => $customerAccount->id,
                'name' => trim((string) data_get($data, 'company.name')),
                'legal_name' => blank(data_get($data, 'company.legal_name')) ? null : trim((string) data_get($data, 'company.legal_name')),
                'tax_id' => blank(data_get($data, 'company.tax_id')) ? null : trim((string) data_get($data, 'company.tax_id')),
                'timezone' => (string) data_get($data, 'company.timezone', 'America/Mexico_City'),
                'status' => (string) data_get($data, 'company.status', 'active'),
                'settings' => [],
            ]);

            $company->setting()->create(Company::defaultSettings());

            $reusedExistingAdministrator = $existingAdministrator !== null;
            $admin = $existingAdministrator ?? User::query()->create([
                'name' => trim((string) data_get($data, 'admin.name')),
                'email' => $email,
                'password' => Hash::make((string) data_get($data, 'admin.password')),
                'status' => (string) data_get($data, 'admin.status', 'active'),
                'global_role' => null,
            ]);

            $admin->companies()->attach($company, [
                'role_id' => $adminRole->id,
                'status' => 'active',
                'is_default' => true,
            ]);

            return [$company, $admin, $reusedExistingAdministrator];
        });

        try {
            $this->sendAdministratorWelcomeEmail->handle($company, $administrator, ! $reusedExistingAdministrator);
        } catch (BrevoDeliveryException $exception) {
            Log::warning('Company administrator welcome email delivery failed.', [
                'company_id' => $company->id,
                'administrator_id' => $administrator->id,
                'status_code' => $exception->statusCode,
            ]);
        }

        return new CreateTenantWithAdminResult($company, $reusedExistingAdministrator);
    }
}
