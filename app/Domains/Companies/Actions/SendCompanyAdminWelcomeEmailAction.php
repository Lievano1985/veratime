<?php

namespace App\Domains\Companies\Actions;

use App\Domains\Integrations\Services\BrevoTransactionalEmailService;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Password;

class SendCompanyAdminWelcomeEmailAction
{
    public function __construct(private readonly BrevoTransactionalEmailService $transactionalEmail) {}

    /**
     * Sends an onboarding message with a short-lived password setup link.
     *
     * The initial password is deliberately never sent by email.
     */
    public function handle(Company $company, User $administrator): bool
    {
        if (! $this->transactionalEmail->isEnabled()) {
            return false;
        }

        $token = Password::broker()->createToken($administrator);
        $setupUrl = route('password.reset', [
            'token' => $token,
            'email' => $administrator->email,
        ]);
        $expiresInMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        $this->transactionalEmail->send(
            recipients: [[
                'email' => $administrator->email,
                'name' => $administrator->name,
            ]],
            subject: 'Tu acceso a '.config('app.name').' está listo',
            htmlContent: view('emails.companies.administrator-welcome', compact('administrator', 'company', 'setupUrl', 'expiresInMinutes'))->render(),
            textContent: implode("\n\n", [
                "Hola {$administrator->name},",
                "Se creó tu acceso como administrador de {$company->name} en ".config('app.name').'.',
                "Para definir tu contraseña y acceder, usa este enlace:\n{$setupUrl}",
                "El enlace vence en {$expiresInMinutes} minutos.",
                'Por seguridad, nunca enviamos contraseñas por correo.',
            ]),
            tags: ['company-admin-welcome'],
        );

        return true;
    }
}
