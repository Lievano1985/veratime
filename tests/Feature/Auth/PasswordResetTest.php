<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.brevo.enabled', false);
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_is_sent_through_brevo_https_when_enabled(): void
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
            'https://api.brevo.test/v3/smtp/email' => Http::response(['messageId' => 'test-message-id'], 201),
        ]);
        $user = User::factory()->create(['name' => 'Ana PÃ©rez']);

        Volt::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors();

        Http::assertSent(function (Request $request) use ($user): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.brevo.test/v3/smtp/email'
                && $request->hasHeader('api-key', 'brevo-test-key')
                && data_get($payload, 'sender.email') === 'soporte@gotvera.test'
                && data_get($payload, 'to.0.email') === $user->email
                && data_get($payload, 'tags.0') === 'password-reset'
                && str_contains((string) data_get($payload, 'subject'), "contrase\u{00F1}a")
                && str_contains((string) data_get($payload, 'htmlContent'), '/reset-password/');
        });
    }

    public function test_password_reset_keeps_a_neutral_response_when_brevo_rejects_delivery(): void
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
        $user = User::factory()->create();

        Volt::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink')
            ->assertHasNoErrors();

        Http::assertSentCount(1);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Volt::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendPasswordResetLink');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = Volt::test('auth.reset-password', ['token' => $notification->token])
                ->set('email', $user->email)
                ->set('password', 'password')
                ->set('password_confirmation', 'password')
                ->call('resetPassword');

            $response
                ->assertHasNoErrors()
                ->assertRedirect(route('login', absolute: false));

            return true;
        });
    }
}
