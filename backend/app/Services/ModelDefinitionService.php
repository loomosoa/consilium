<?php

namespace App\Services;

class ModelDefinitionService
{
    /** @return array<int, array{code: string, providerName: string, displayName: string, label: string, openRouterModelId: string, contextWindow: int, order: int, enabled: bool}> */
    public function all(): array
    {
        $premium = array_filter(config('models.premium', []), fn (array $m) => $m['enabled']);
        $free = array_filter(config('models.free', []), fn (array $m) => $m['enabled']);

        return array_merge($premium, $free);
    }

    /** @return array<int, array{code: string, providerName: string, displayName: string, label: string, openRouterModelId: string, contextWindow: int, order: int, enabled: bool}> */
    public function premium(): array
    {
        $models = array_filter(config('models.premium', []), fn (array $m) => $m['enabled']);
        usort($models, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $models;
    }

    /** @return array<int, array{code: string, providerName: string, displayName: string, label: string, openRouterModelId: string, contextWindow: int, order: int, enabled: bool}> */
    public function free(): array
    {
        $models = array_filter(config('models.free', []), fn (array $m) => $m['enabled']);
        usort($models, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $models;
    }

    /** @return array<int, array{code: string, providerName: string, displayName: string, label: string, openRouterModelId: string, contextWindow: int, order: int, enabled: bool}> */
    public function active(): array
    {
        // По умолчанию используем free модели
        return $this->free();
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
