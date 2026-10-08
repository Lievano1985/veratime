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
     * Sends a secure onboarding or access-granted message.
     *
     * An existing account keeps its password and only receives an access notice.
     */
    public function handle(Company $company, User $administrator, bool $isNewAdministrator): bool
    {
        if (! $this->transactionalEmail->isEnabled()) {
            return false;
        }

        $setupUrl = null;
        $expiresInMinutes = null;

        if ($isNewAdministrator) {
            $token = Password::broker()->createToken($administrator);
            $setupUrl = route('password.reset', [
                'token' => $token,
                'email' => $administrator->email,
            ]);
            $expiresInMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        }

        $subject = $isNewAdministrator
            ? 'Tu acceso a '.config('app.name').' está listo'
            : 'Tienes acceso a una nueva empresa en '.config('app.name');
        $textContent = $isNewAdministrator
            ? implode("\n\n", [
                "Hola {$administrator->name},",
                "Se creó tu acceso como administrador de {$company->name} en ".config('app.name').'.',
                "Para definir tu contraseña y acceder, usa este enlace:\n{$setupUrl}",
                "El enlace vence en {$expiresInMinutes} minutos.",
                'Por seguridad, nunca enviamos contraseñas por correo.',
            ])
            : implode("\n\n", [
                "Hola {$administrator->name},",
                "Tu cuenta existente ahora tiene acceso como administrador de {$company->name} en ".config('app.name').'.',
                'Tu contraseña no fue modificada. Puedes ingresar desde '.route('login').'.',
            ]);

        $this->transactionalEmail->send(
            recipients: [[
                'email' => $administrator->email,
                'name' => $administrator->name,
            ]],
            subject: $subject,
            htmlContent: view('emails.companies.administrator-welcome', compact('administrator', 'company', 'setupUrl', 'expiresInMinutes', 'isNewAdministrator'))->render(),
            textContent: $textContent,
            tags: [$isNewAdministrator ? 'company-admin-welcome' : 'company-admin-access-granted'],
        );

        return true;
    }
}
