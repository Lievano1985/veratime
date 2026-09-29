<?php

namespace App\Domains\Identity\Actions;

use App\Domains\Integrations\Exceptions\BrevoDeliveryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class RequestPasswordResetAction
{
    public function handle(string $email): string
    {
        try {
            return Password::sendResetLink(['email' => $email]);
        } catch (BrevoDeliveryException $exception) {
            Log::error('Password reset delivery failed.', [
                'provider' => 'brevo',
                'status_code' => $exception->statusCode,
            ]);

            return Password::RESET_LINK_SENT;
        } catch (Throwable $exception) {
            Log::error('Password reset delivery failed.', [
                'exception' => $exception::class,
            ]);

            return Password::RESET_LINK_SENT;
        }
    }
}
