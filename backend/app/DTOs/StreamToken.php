<?php

namespace App\DTOs;

readonly class StreamToken
{
    public function __construct(
        public string $text,
        public int $sequence,
    ) {}
}
