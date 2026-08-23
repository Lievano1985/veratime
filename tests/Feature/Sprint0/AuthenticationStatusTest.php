<?php

namespace Tests\Feature\Sprint0;

use App\Models\User;
use App\Models\Company;
use App\Models\Role;
use App\Support\RoleKey;
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

    public function test_authenticated_user_is_logged_out_when_marked_inactive(): void
    {
        $role = Role::factory()->create(['key' => RoleKey::ADMIN_EMPRESA]);
        $company = Company::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['status' => 'active']);

        $user->companies()->attach($company, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->actingAs($user)->withSession(['current_company_id' => $company->id]);

        $user->forceFill(['status' => 'inactive'])->save();

        $this->get('/companies')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
