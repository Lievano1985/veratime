<?php

namespace App\Domains\Identity\Actions;

use Illuminate\Support\Facades\Password;

class RequestPasswordResetAction
{
    public function handle(string $email): string
    {
        return Password::sendResetLink(['email' => $email]);
    }
}
