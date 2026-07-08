<?php

namespace Tests\Feature\Sprint0;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt as LivewireVolt;
use Tests\TestCase;

class AuthenticationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_users_cannot_authenticate(): void
    {
        $user = User::factory()->create([
            'status' => 'inactive',
        ]);

        LivewireVolt::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }
}
