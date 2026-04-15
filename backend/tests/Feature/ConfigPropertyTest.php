<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfigPropertyTest extends TestCase
{
    /**
     * Prop. 20: при отсутствии ключа в .env система сигнализирует о необходимости ручного ввода.
     */
    #[Test]
    public function config_signals_api_key_required_when_no_env_key(): void
    {
        config(['services.openrouter.key' => null]);

        $response = $this->getJson('/api/config');

        $response->assertOk();
        $response->assertJson(['apiKeyRequired' => true]);
    }

    #[Test]
    public function config_signals_no_api_key_required_when_env_key_present(): void
    {
        config(['services.openrouter.key' => 'sk-env-key-1234567890ab']);

        $response = $this->getJson('/api/config');

        $response->assertOk();
        $response->assertJson(['apiKeyRequired' => false]);
    }

    #[Test]
    public function config_always_returns_consistent_layout(): void
    {
        $response = $this->getJson('/api/config');

        $response->assertOk();

        $models = $response->json('models');
        $desktopColumns = $response->json('layout.desktopColumns');

        // desktopColumns must match the number of active models
        $this->assertCount($desktopColumns, $models);
    }
}
