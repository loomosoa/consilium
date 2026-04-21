<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ModelDefinition
{
    public function __construct(
        public string $code,
        public string $providerName,
        public string $displayName,
        public string $label,
        public string $openRouterModelId,
        public int $contextWindow,
        public int $order,
        public bool $enabled,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['code'],
            providerName: $data['providerName'],
            displayName: $data['displayName'],
            label: $data['label'],
            openRouterModelId: $data['openRouterModelId'],
            contextWindow: $data['contextWindow'],
            order: $data['order'],
            enabled: $data['enabled'],
        );
    }
}
