<?php

namespace App\Services\AI\Exceptions;

class AiNotConfiguredException extends AiException
{
    public function __construct(string $message = 'AI analysis is not available right now because the AI provider is not configured on the server.')
    {
        parent::__construct($message, 503);
    }
}
