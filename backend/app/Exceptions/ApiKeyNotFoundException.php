<?php

declare(strict_types=1);

namespace App\Exceptions;

class ApiKeyNotFoundException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'OpenRouter API key is not configured. Set OPENROUTER_API_KEY in .env or provide it via the session.'
        );
    }
}
