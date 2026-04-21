<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ColumnDto
{
    public function __construct(
        public string $id,
        public string $modelCode,
        public int $position,
        public string $status,
    ) {}
}
