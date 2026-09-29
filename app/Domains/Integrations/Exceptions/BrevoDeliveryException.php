<?php

namespace App\Domains\Integrations\Exceptions;

use RuntimeException;

class BrevoDeliveryException extends RuntimeException
{
    public function __construct(public readonly ?int $statusCode = null)
    {
        parent::__construct('The transactional email provider could not deliver the message.');
    }
}
