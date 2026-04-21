<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ModelDefinition;

class ModelDefinitionService
{
    /** @return ModelDefinition[] */
    public function all(): array
    {
        $premium = $this->mapEnabled(config('models.premium', []));
        $free = $this->mapEnabled(config('models.free', []));

        return array_merge($premium, $free);
    }

    /** @return ModelDefinition[] */
    public function premium(): array
    {
        return $this->mapEnabled(config('models.premium', []));
    }

    /** @return ModelDefinition[] */
    public function free(): array
    {
        return $this->mapEnabled(config('models.free', []));
    }

    /** @return ModelDefinition[] */
    public function active(): array
    {
        // По умолчанию используем free модели
        return $this->free();
    }

    public function findByCode(string $code): ?ModelDefinition
    {
        foreach ($this->all() as $model) {
            if ($model->code === $code) {
                return $model;
            }
        }

        return null;
    }

    public function smallestContextWindow(): int
    {
        return collect($this->active())->min('contextWindow') ?? 0;
    }

    /** @return ModelDefinition[] */
    private function mapEnabled(array $raw): array
    {
        $models = array_filter($raw, fn (array $m) => $m['enabled']);
        $dtos = array_map(fn (array $m) => ModelDefinition::fromArray($m), $models);
        usort($dtos, fn (ModelDefinition $a, ModelDefinition $b) => $a->order <=> $b->order);

        return $dtos;
    }
}
