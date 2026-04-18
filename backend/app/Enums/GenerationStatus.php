<?php

namespace App\Enums;

enum GenerationStatus: string
{
    case PENDING = 'pending';
    case STREAMING = 'streaming';
    case COMPLETED = 'completed';
    case ERROR = 'error';
    case CANCELLED = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::PENDING, self::STREAMING]);
    }

    public function isRetryable(): bool
    {
        return in_array($this, [self::ERROR, self::CANCELLED]);
    }
}
