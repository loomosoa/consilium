<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class GenerationDto
{
    public function __construct(
        public string $id,
        public string $columnId,
        public string $userMessageId,
        public string $status,
    ) {}
}
