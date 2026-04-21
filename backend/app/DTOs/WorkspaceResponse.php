<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class WorkspaceResponse
{
    /**
     * @param  ColumnDto[]  $columns
     * @param  GenerationDto[]  $generations
     */
    public function __construct(
        public string $workspaceId,
        public array $columns,
        public array $generations,
    ) {}
}
