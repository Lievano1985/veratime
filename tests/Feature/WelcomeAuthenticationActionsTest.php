<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WelcomeAuthenticationActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_login_actions_on_the_welcome_page(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Iniciar sesión')
            ->assertDontSee('Cerrar sesión');
    }

    public function test_authenticated_user_sees_logout_actions_on_the_welcome_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertOk()
            ->assertSee('Cerrar sesión')
            ->assertDontSee('Iniciar sesión');
    }
}
