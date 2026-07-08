<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_route_is_disabled(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }

    public function test_public_registration_component_does_not_create_active_user(): void
    {
        Volt::test('auth.register')->call('register');

        $this->assertDatabaseCount(User::class, 0);
        $this->assertGuest();
    }
}
