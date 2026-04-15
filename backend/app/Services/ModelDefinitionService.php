<?php

namespace App\Services;

class ModelDefinitionService
{
    /** @return array<int, array{code: string, providerName: string, displayName: string, label: string, openRouterModelId: string, contextWindow: int, order: int, enabled: bool}> */
    public function all(): array
    {
        return array_filter(config('models.definitions', []), fn (array $m) => $m['enabled']);
    }

    /** @return array<int, array{code: string, providerName: string, displayName: string, label: string, openRouterModelId: string, contextWindow: int, order: int, enabled: bool}> */
    public function active(): array
    {
        $active = $this->all();
        usort($active, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $active;
    }

    public function findByCode(string $code): ?array
    {
        foreach ($this->all() as $model) {
            if ($model['code'] === $code) {
                return $model;
            }
        }

        return null;
    }

    public function smallestContextWindow(): int
    {
        return collect($this->active())->min('contextWindow') ?? 0;
    }
}
