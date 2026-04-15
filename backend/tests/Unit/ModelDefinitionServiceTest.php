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
    public function returns_exactly_4_premium_models(): void
    {
        $premium = $this->service->premium();

        $this->assertCount(4, $premium);
    }

    #[Test]
    public function returns_exactly_4_free_models(): void
    {
        $free = $this->service->free();

        $this->assertCount(4, $free);
    }

    #[Test]
    public function returns_8_models_in_total(): void
    {
        $all = $this->service->all();

        $this->assertCount(8, $all);
    }

    #[Test]
    public function active_returns_free_models(): void
    {
        $active = $this->service->active();

        $this->assertCount(4, $active);
        $this->assertEquals($this->service->free(), $active);
    }

    #[Test]
    public function all_codes_are_unique(): void
    {
        $all = $this->service->all();
        $codes = array_map(fn ($m) => $m->code, $all);

        $this->assertCount(8, array_unique($codes));
    }

    #[Test]
    public function premium_codes_are_unique(): void
    {
        $premium = $this->service->premium();
        $codes = array_map(fn ($m) => $m->code, $premium);

        $this->assertCount(4, array_unique($codes));
    }

    #[Test]
    public function free_codes_are_unique(): void
    {
        $free = $this->service->free();
        $codes = array_map(fn ($m) => $m->code, $free);

        $this->assertCount(4, array_unique($codes));
    }

    #[Test]
    public function premium_orders_are_unique(): void
    {
        $premium = $this->service->premium();
        $orders = array_map(fn ($m) => $m->order, $premium);

        $this->assertCount(4, array_unique($orders));
    }

    #[Test]
    public function premium_models_are_sorted_by_order(): void
    {
        $premium = $this->service->premium();
        $orders = array_map(fn ($m) => $m->order, $premium);

        $sorted = $orders;
        sort($sorted);

        $this->assertEquals($sorted, $orders);
    }

    #[Test]
    public function free_models_are_sorted_by_order(): void
    {
        $free = $this->service->free();
        $orders = array_map(fn ($m) => $m->order, $free);

        $sorted = $orders;
        sort($sorted);

        $this->assertEquals($sorted, $orders);
    }

    #[Test]
    public function find_by_code_returns_premium_model(): void
    {
        $model = $this->service->findByCode('xai');

        $this->assertNotNull($model);
        $this->assertEquals('xai', $model->code);
        $this->assertEquals('xAI', $model->providerName);
    }

    #[Test]
    public function find_by_code_returns_free_model(): void
    {
        $model = $this->service->findByCode('nvidia');

        $this->assertNotNull($model);
        $this->assertEquals('nvidia', $model->code);
        $this->assertEquals('NVIDIA', $model->providerName);
    }

    #[Test]
    public function find_by_code_returns_null_for_unknown(): void
    {
        $this->assertNull($this->service->findByCode('nonexistent'));
    }

    #[Test]
    public function smallest_context_window_returns_minimum_from_free(): void
    {
        // Минимум среди free моделей: arcee (8K) и openai-free (8K)
        $this->assertEquals(8192, $this->service->smallestContextWindow());
    }

    #[Test]
    public function all_premium_models_have_openrouter_model_id(): void
    {
        $premium = $this->service->premium();

        foreach ($premium as $model) {
            $this->assertNotEmpty($model->openRouterModelId);
        }
    }

    #[Test]
    public function all_free_models_have_openrouter_model_id(): void
    {
        $free = $this->service->free();

        foreach ($free as $model) {
            $this->assertNotEmpty($model->openRouterModelId);
            $this->assertStringEndsWith(':free', $model->openRouterModelId);
        }
    }
}
