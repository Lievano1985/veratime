<?php

namespace App\Domains\Marketing\Actions;

use App\Domains\Integrations\Exceptions\BrevoDeliveryException;
use App\Domains\Integrations\Services\BrevoTransactionalEmailService;
use App\Models\DemoRequest;

class SendDemoRequestNotificationWithBrevoAction
{
    public function __construct(private readonly BrevoTransactionalEmailService $transactionalEmail) {}

    public function handle(DemoRequest $demoRequest): void
    {
        if (! $this->transactionalEmail->isEnabled()) {
            return;
        }

        $recipient = config('services.brevo.contact_recipient');

        if (! is_string($recipient) || $recipient === '') {
            throw new BrevoDeliveryException;
        }

        $this->transactionalEmail->send(
            recipients: [['email' => $recipient]],
            subject: 'Nueva solicitud de demostraciÃ³n en '.config('app.name'),
            htmlContent: view('emails.marketing.new-demo-request', compact('demoRequest'))->render(),
            textContent: implode("\n", [
                'Nueva solicitud de demostraciÃ³n',
                "Nombre: {$demoRequest->contact_name}",
                'Empresa: '.($demoRequest->company_name ?: 'No indicada'),
                "Correo: {$demoRequest->email}",
                "TelÃ©fono: {$demoRequest->phone}",
                'TamaÃ±o de equipo: '.($demoRequest->team_size ?: 'No indicado'),
                'Mensaje: '.($demoRequest->message ?: 'Sin mensaje'),
            ]),
            tags: ['demo-request'],
            replyTo: [
                'email' => $demoRequest->email,
                'name' => $demoRequest->contact_name,
            ],
        );
    }
}
