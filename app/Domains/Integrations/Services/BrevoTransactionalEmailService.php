<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Integrations\Exceptions\BrevoDeliveryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class BrevoTransactionalEmailService
{
    public function isEnabled(): bool
    {
        return (bool) config('services.brevo.enabled');
    }

    /**
     * @param  list<array{email: string, name?: string}>  $recipients
     * @param  list<string>  $tags
     * @param  array{email: string, name?: string}|null  $replyTo
     */
    public function send(
        array $recipients,
        string $subject,
        string $htmlContent,
        string $textContent,
        array $tags = [],
        ?array $replyTo = null,
    ): void {
        $apiKey = config('services.brevo.api_key');
        $senderAddress = config('services.brevo.sender.address');
        $senderName = config('services.brevo.sender.name');

        if (! $this->isEnabled() || ! is_string($apiKey) || $apiKey === '' || ! is_string($senderAddress) || $senderAddress === '') {
            throw new BrevoDeliveryException;
        }

        $payload = [
            'sender' => array_filter([
                'email' => $senderAddress,
                'name' => is_string($senderName) && $senderName !== '' ? $senderName : null,
            ]),
            'to' => $recipients,
            'subject' => $subject,
            'htmlContent' => $htmlContent,
            'textContent' => $textContent,
            'tags' => $tags,
        ];

        if ($replyTo !== null) {
            $payload['replyTo'] = $replyTo;
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['api-key' => $apiKey])
                ->timeout((int) config('services.brevo.timeout', 10))
                ->post((string) config('services.brevo.endpoint'), $payload);
        } catch (ConnectionException) {
            throw new BrevoDeliveryException;
        }

        if (! $response->successful()) {
            throw new BrevoDeliveryException($response->status());
        }
    }
}
