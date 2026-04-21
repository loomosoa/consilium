<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfigControllerTest extends TestCase
{
    #[Test]
    public function returns_active_models_with_required_fields(): void
    {
        $response = $this->getJson('/api/config');

        $response->assertOk();
        $response->assertJsonStructure([
            'models' => [
                '*' => ['code', 'providerName', 'displayName', 'label', 'openRouterModelId', 'contextWindow', 'order'],
            ],
            'apiKeyRequired',
            'layout' => ['desktopColumns'],
        ]);
    }

    #[Test]
    public function returns_4_active_models(): void
    {
        $response = $this->getJson('/api/config');

        $response->assertOk();
        $this->assertCount(4, $response->json('models'));
    }

    #[Test]
    public function models_are_sorted_by_order(): void
    {
        $response = $this->getJson('/api/config');

        $orders = collect($response->json('models'))->pluck('order')->toArray();
        $sorted = $orders;
        sort($sorted);

        $this->assertEquals($sorted, $orders);
    }

    #[Test]
    public function api_key_required_is_true_when_no_env_key(): void
    {
        config(['services.openrouter.key' => null]);

        $response = $this->getJson('/api/config');

        $response->assertOk();
        $response->assertJson(['apiKeyRequired' => true]);
    }

    #[Test]
    public function api_key_required_is_false_when_env_key_present(): void
    {
        config(['services.openrouter.key' => 'sk-test-key-1234567890ab']);

        $response = $this->getJson('/api/config');

        $response->assertOk();
        $response->assertJson(['apiKeyRequired' => false]);
    }

    #[Test]
    public function desktop_columns_matches_model_count(): void
    {
        $response = $this->getJson('/api/config');

        $response->assertOk();
        $modelCount = count($response->json('models'));
        $desktopColumns = $response->json('layout.desktopColumns');

        $this->assertEquals($modelCount, $desktopColumns);
    }

    #[Test]
    public function free_models_have_free_suffix_in_id(): void
    {
        $response = $this->getJson('/api/config');

        $models = $response->json('models');
        foreach ($models as $model) {
            $this->assertStringEndsWith(':free', $model['openRouterModelId']);
        }
    }
}
