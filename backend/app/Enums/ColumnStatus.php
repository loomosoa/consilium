<?php

namespace App\Enums;

enum ColumnStatus: string
{
    case IDLE = 'idle';
    case WAITING = 'waiting';
    case STREAMING = 'streaming';
    case COMPLETED = 'completed';
    case ERROR = 'error';
    case CANCELLED = 'cancelled';
}
