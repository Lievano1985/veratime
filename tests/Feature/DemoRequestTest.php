<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitor_can_submit_a_demo_request(): void
    {
        $response = $this->post(route('demo-requests.store'), [
            'contact_name' => 'Ana Pérez',
            'company_name' => 'Empresa Ejemplo',
            'email' => 'ana@example.com',
            'phone' => '6621234567',
            'team_size' => 50,
            'message' => 'Quiero revisar la programación de turnos.',
            'consent' => '1',
        ]);

        $response->assertRedirect(route('home').'#demo');
        $response->assertSessionHas('demo_request_success');
        $this->assertDatabaseHas('demo_requests', [
            'contact_name' => 'Ana Pérez',
            'company_name' => 'Empresa Ejemplo',
            'email' => 'ana@example.com',
            'phone' => '6621234567',
            'team_size' => 50,
            'status' => 'new',
            'source' => 'welcome',
        ]);
    }

    public function test_demo_request_requires_contact_information_and_consent(): void
    {
        $response = $this->from(route('home'))->post(route('demo-requests.store'), []);

        $response->assertRedirect(route('home'));
        $response->assertSessionHasErrorsIn('demoRequest', [
            'contact_name',
            'email',
            'phone',
            'consent',
        ]);
        $this->assertDatabaseCount('demo_requests', 0);
    }

    public function test_demo_request_notifies_the_configured_support_inbox_through_brevo(): void
    {
        config()->set('services.brevo', [
            'enabled' => true,
            'api_key' => 'brevo-test-key',
            'endpoint' => 'https://api.brevo.test/v3/smtp/email',
            'timeout' => 10,
            'sender' => ['address' => 'soporte@gotvera.test', 'name' => 'VERA Time'],
            'contact_recipient' => 'ventas@gotvera.test',
        ]);
        Http::fake([
            'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'test-message-id'], 201),
        ]);

        $this->post(route('demo-requests.store'), [
            'contact_name' => 'Ana PÃ©rez',
            'company_name' => 'Empresa Ejemplo',
            'email' => 'ana@example.com',
            'phone' => '6621234567',
            'team_size' => 50,
            'message' => 'Quiero revisar la programaciÃ³n de turnos.',
            'consent' => '1',
        ])->assertRedirect(route('home').'#demo');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.brevo.test/v3/smtp/email'
                && data_get($payload, 'to.0.email') === 'ventas@gotvera.test'
                && data_get($payload, 'replyTo.email') === 'ana@example.com'
                && data_get($payload, 'tags.0') === 'demo-request';
        });
    }

    public function test_demo_request_is_preserved_when_brevo_notification_fails(): void
    {
        config()->set('services.brevo', [
            'enabled' => true,
            'api_key' => 'brevo-test-key',
            'endpoint' => 'https://api.brevo.test/v3/smtp/email',
            'timeout' => 10,
            'sender' => ['address' => 'soporte@gotvera.test', 'name' => 'VERA Time'],
            'contact_recipient' => 'ventas@gotvera.test',
        ]);
        Http::fake([
            'https://api.brevo.test/v3/smtp/email' => Http::response(['message' => 'Rejected'], 400),
        ]);

        $this->post(route('demo-requests.store'), [
            'contact_name' => 'Ana PÃ©rez',
            'email' => 'ana@example.com',
            'phone' => '6621234567',
            'consent' => '1',
        ])->assertRedirect(route('home').'#demo');

        $this->assertDatabaseHas('demo_requests', [
            'email' => 'ana@example.com',
            'status' => 'new',
        ]);
    }

    public function test_welcome_page_links_to_key_sections_and_contains_demo_modal(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="#producto"', false)
            ->assertSee('href="#como-funciona"', false)
            ->assertSee('href="#reforma"', false)
            ->assertSee('href="#kiosco"', false)
            ->assertSee('href="#demo"', false)
            ->assertSee('data-demo-modal', false)
            ->assertSee('data-demo-modal-open', false);
    }
}
