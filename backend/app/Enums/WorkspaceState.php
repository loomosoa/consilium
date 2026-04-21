<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkspaceState: string
{
    case INITIALIZING = 'initializing';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case ARCHIVED = 'archived';
}
