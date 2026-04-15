<?php

namespace Tests\Unit;

use App\Services\ModelDefinitionService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelDefinitionServiceTest extends TestCase
{
    private ModelDefinitionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ModelDefinitionService;
    }

    #[Test]
    public function returns_exactly_4_active_models(): void
    {
        $active = $this->service->active();

        $this->assertCount(4, $active);
    }

    #[Test]
    public function codes_are_unique(): void
    {
        $active = $this->service->active();
        $codes = array_column($active, 'code');

        $this->assertCount(4, array_unique($codes));
    }

    #[Test]
    public function orders_are_unique(): void
    {
        $active = $this->service->active();
        $orders = array_column($active, 'order');

        $this->assertCount(4, array_unique($orders));
    }

    #[Test]
    public function active_models_are_sorted_by_order(): void
    {
        $active = $this->service->active();
        $orders = array_column($active, 'order');

        $sorted = $orders;
        sort($sorted);

        $this->assertEquals($sorted, $orders);
    }

    #[Test]
    public function find_by_code_returns_correct_model(): void
    {
        $model = $this->service->findByCode('xai');

        $this->assertNotNull($model);
        $this->assertEquals('xai', $model['code']);
        $this->assertEquals('xAI', $model['providerName']);
        $this->assertEquals('Grok 4.20', $model['displayName']);
    }

    #[Test]
    public function find_by_code_returns_null_for_unknown(): void
    {
        $this->assertNull($this->service->findByCode('nonexistent'));
    }

    #[Test]
    public function smallest_context_window_returns_minimum(): void
    {
        $this->assertEquals(128000, $this->service->smallestContextWindow());
    }

    #[Test]
    public function all_models_have_openrouter_model_id(): void
    {
        $active = $this->service->active();

        foreach ($active as $model) {
            $this->assertNotEmpty($model['openRouterModelId']);
        }
    }
}
