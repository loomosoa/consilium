<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class UpstreamError
{
    public function __construct(
        public string $code,
        public string $message,
        public bool $retryable,
        public ?int $httpStatus = null,
    ) {}
}
