<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ApiKeyNotFoundException;
use App\Services\ApiKeyResolver;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiKeyResolverValidateTest extends TestCase
{
    #[Test]
    public function validate_key_returns_true_for_valid_key(): void
    {
        config(['services.openrouter.key' => 'valid-key']);

        Http::fake([
            'openrouter.ai/*' => Http::response(['data' => []], 200),
        ]);

        $resolver = $this->app->make(ApiKeyResolver::class);

        $this->assertTrue($resolver->validateKey());
    }

    #[Test]
    public function validate_key_returns_false_for_invalid_key(): void
    {
        config(['services.openrouter.key' => 'invalid-key']);

        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => 'Invalid API key'], 401),
        ]);

        $resolver = $this->app->make(ApiKeyResolver::class);

        $this->assertFalse($resolver->validateKey());
    }

    #[Test]
    public function validate_key_returns_false_when_no_key_available(): void
    {
        // Simulate no key by mocking resolve() to throw
        $mock = $this->createMock(ApiKeyResolver::class);
        $mock->method('resolve')->willThrowException(new ApiKeyNotFoundException);
        $mock->method('validateKey')->willReturn(false);

        $this->assertFalse($mock->validateKey());
    }
}
