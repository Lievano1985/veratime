<?php

namespace Tests\Feature\Companies;

use App\Domains\Companies\Actions\CreateTenantWithAdminAction;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyAdminWelcomeEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_creation_sends_a_secure_welcome_email_to_the_new_administrator(): void
    {
        config()->set('services.brevo', [
            'enabled' => true,
            'api_key' => 'brevo-test-key',
            'endpoint' => 'https://api.brevo.test/v3/smtp/email',
            'timeout' => 10,
            'sender' => ['address' => 'soporte@gotvera.test', 'name' => 'VERA Time'],
            'contact_recipient' => 'soporte@gotvera.test',
        ]);
        Http::fake([
            'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'welcome-message-id'], 201),
        ]);

        Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $result = app(CreateTenantWithAdminAction::class)->handle($superAdmin, [
            'company' => [
                'name' => 'Empresa con bienvenida',
                'legal_name' => 'Empresa con Bienvenida SA de CV',
                'tax_id' => 'BIE261007AA1',
                'timezone' => 'America/Mexico_City',
                'status' => 'active',
            ],
            'admin' => [
                'name' => 'Admin Bienvenida',
                'email' => 'admin.bienvenida@example.test',
                'password' => 'AdminDemo1!',
                'status' => 'active',
            ],
        ]);

        $this->assertDatabaseHas('companies', ['id' => $result->company->id]);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.brevo.test/v3/smtp/email'
                && $request->hasHeader('api-key', 'brevo-test-key')
                && data_get($payload, 'to.0.email') === 'admin.bienvenida@example.test'
                && data_get($payload, 'tags.0') === 'company-admin-welcome'
                && str_contains((string) data_get($payload, 'subject'), 'acceso')
                && str_contains((string) data_get($payload, 'htmlContent'), '/reset-password/')
                && str_contains((string) data_get($payload, 'htmlContent'), 'Datos de acceso')
                && str_contains((string) data_get($payload, 'htmlContent'), 'admin.bienvenida@example.test')
                && str_contains((string) data_get($payload, 'htmlContent'), 'images/veralogo.png')
                && ! str_contains((string) data_get($payload, 'htmlContent'), 'AdminDemo1!');
        });
    }

    public function test_tenant_creation_is_not_reverted_when_the_welcome_email_cannot_be_delivered(): void
    {
        config()->set('services.brevo', [
            'enabled' => true,
            'api_key' => 'brevo-test-key',
            'endpoint' => 'https://api.brevo.test/v3/smtp/email',
            'timeout' => 10,
            'sender' => ['address' => 'soporte@gotvera.test', 'name' => 'VERA Time'],
            'contact_recipient' => 'soporte@gotvera.test',
        ]);
        Http::fake([
            'https://api.brevo.test/v3/smtp/email' => Http::response(['message' => 'Rejected'], 400),
        ]);

        Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);

        $result = app(CreateTenantWithAdminAction::class)->handle($superAdmin, [
            'company' => [
                'name' => 'Empresa entrega fallida',
                'legal_name' => 'Empresa Entrega Fallida SA de CV',
                'tax_id' => 'FAL261007AA1',
                'timezone' => 'America/Mexico_City',
                'status' => 'active',
            ],
            'admin' => [
                'name' => 'Admin Entrega Fallida',
                'email' => 'admin.entrega.fallida@example.test',
                'password' => 'AdminDemo1!',
                'status' => 'active',
            ],
        ]);

        $this->assertDatabaseHas('companies', ['id' => $result->company->id]);
        $this->assertDatabaseHas('users', ['email' => 'admin.entrega.fallida@example.test']);
        Http::assertSentCount(1);
    }

    public function test_tenant_creation_reuses_an_existing_user_without_company_memberships(): void
    {
        config()->set('services.brevo', [
            'enabled' => true,
            'api_key' => 'brevo-test-key',
            'endpoint' => 'https://api.brevo.test/v3/smtp/email',
            'timeout' => 10,
            'sender' => ['address' => 'soporte@gotvera.test', 'name' => 'VERA Time'],
            'contact_recipient' => 'soporte@gotvera.test',
        ]);
        Http::fake([
            'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'access-message-id'], 201),
        ]);

        $adminRole = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $superAdmin = User::factory()->create([
            'status' => 'active',
            'global_role' => RoleKey::SUPER_ADMIN,
        ]);
        $existingAdministrator = User::factory()->create([
            'name' => 'Cuenta existente',
            'email' => 'cuenta.existente@example.test',
            'status' => 'active',
            'global_role' => null,
        ]);
        $originalPassword = $existingAdministrator->password;

        $result = app(CreateTenantWithAdminAction::class)->handle($superAdmin, [
            'company' => [
                'name' => 'Empresa con cuenta existente',
                'legal_name' => 'Empresa con Cuenta Existente SA de CV',
                'tax_id' => 'EXI261007AA1',
                'timezone' => 'America/Mexico_City',
                'status' => 'active',
            ],
            'admin' => [
                'name' => 'Nombre que no reemplaza la cuenta',
                'email' => $existingAdministrator->email,
                'password' => 'OtraClave1!',
                'status' => 'active',
            ],
        ]);

        $this->assertTrue($result->reusedExistingAdministrator);
        $this->assertSame($originalPassword, $existingAdministrator->fresh()->password);
        $this->assertDatabaseHas('company_user', [
            'company_id' => $result->company->id,
            'user_id' => $existingAdministrator->id,
            'role_id' => $adminRole->id,
            'status' => 'active',
            'is_default' => true,
        ]);
        Http::assertSent(function (Request $request) use ($existingAdministrator): bool {
            $payload = $request->data();

            return data_get($payload, 'to.0.email') === $existingAdministrator->email
                && data_get($payload, 'tags.0') === 'company-admin-access-granted'
                && str_contains((string) data_get($payload, 'htmlContent'), '/reset-password/')
                && str_contains((string) data_get($payload, 'htmlContent'), 'Definir o restablecer');
        });
    }
}
