<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
