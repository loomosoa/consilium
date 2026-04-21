<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class StreamCompleted
{
    public function __construct(
        public string $finishReason,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
    ) {}
}
