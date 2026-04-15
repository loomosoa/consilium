<?php

namespace App\Enums;

enum WorkspaceState: string
{
    case INITIALIZING = 'initializing';
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case ARCHIVED = 'archived';
}
