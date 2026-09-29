<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Integrations\Services\BrevoTransactionalEmailService;
use App\Models\User;

class SendPasswordResetWithBrevoAction
{
    public function __construct(private readonly BrevoTransactionalEmailService $transactionalEmail) {}

    public function handle(User $user, string $token): void
    {
        $resetUrl = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);
        $expiresInMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        $this->transactionalEmail->send(
            recipients: [[
                'email' => $user->email,
                'name' => $user->name,
            ]],
            subject: 'Restablece tu contraseÃ±a de '.config('app.name'),
            htmlContent: view('emails.auth.reset-password', compact('user', 'resetUrl', 'expiresInMinutes'))->render(),
            textContent: "Hola {$user->name},\n\nRecibimos una solicitud para restablecer tu contraseÃ±a. Usa este enlace:\n{$resetUrl}\n\nEste enlace vence en {$expiresInMinutes} minutos. Si no solicitaste el cambio, no necesitas hacer nada.",
            tags: ['password-reset'],
        );
    }
}
