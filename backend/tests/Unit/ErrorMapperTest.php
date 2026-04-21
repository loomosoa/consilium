<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\DTOs\UpstreamError;
use App\Services\ErrorMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ErrorMapperTest extends TestCase
{
    private ErrorMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new ErrorMapper;
    }

    #[Test]
    public function maps_rate_limit_to_retryable_error(): void
    {
        $error = $this->mapper->map('rate_limit');

        $this->assertEquals('rate_limit', $error->code);
        $this->assertTrue($error->retryable);
        $this->assertStringContainsString('Rate limit', $error->message);
    }

    #[Test]
    public function maps_provider_unavailable_to_retryable_error(): void
    {
        $error = $this->mapper->map('provider_unavailable');

        $this->assertEquals('provider_unavailable', $error->code);
        $this->assertTrue($error->retryable);
    }

    #[Test]
    public function maps_timeout_to_retryable_error(): void
    {
        $error = $this->mapper->map('timeout');

        $this->assertEquals('timeout', $error->code);
        $this->assertTrue($error->retryable);
    }

    #[Test]
    public function maps_auth_error_to_non_retryable_error(): void
    {
        $error = $this->mapper->map('auth_error');

        $this->assertEquals('auth_error', $error->code);
        $this->assertFalse($error->retryable);
    }

    #[Test]
    public function maps_context_exceeded_to_non_retryable_error(): void
    {
        $error = $this->mapper->map('context_exceeded');

        $this->assertEquals('context_exceeded', $error->code);
        $this->assertFalse($error->retryable);
    }

    #[Test]
    public function maps_unknown_code_to_upstream_error_with_fallback(): void
    {
        $error = $this->mapper->map('unknown_code', 'Custom fallback');

        $this->assertEquals('upstream_error', $error->code);
        $this->assertTrue($error->retryable);
        $this->assertEquals('Custom fallback', $error->message);
    }

    #[Test]
    public function maps_unknown_code_without_fallback(): void
    {
        $error = $this->mapper->map('unknown_code');

        $this->assertEquals('upstream_error', $error->code);
        $this->assertTrue($error->retryable);
        $this->assertStringContainsString('unexpected', $error->message);
    }

    #[Test]
    public function maps_openrouter_rate_limit_error(): void
    {
        $error = $this->mapper->mapFromOpenRouter(['code' => '429', 'message' => 'Too many requests']);

        $this->assertEquals('rate_limit', $error->code);
        $this->assertTrue($error->retryable);
    }

    #[Test]
    public function maps_openrouter_context_length_exceeded(): void
    {
        $error = $this->mapper->mapFromOpenRouter(['code' => 'context_length_exceeded', 'message' => 'Too long']);

        $this->assertEquals('context_exceeded', $error->code);
        $this->assertFalse($error->retryable);
    }

    #[Test]
    public function maps_openrouter_server_error(): void
    {
        $error = $this->mapper->mapFromOpenRouter(['code' => 'server_error', 'message' => 'Internal error']);

        $this->assertEquals('provider_unavailable', $error->code);
        $this->assertTrue($error->retryable);
    }

    #[Test]
    public function maps_openrouter_unknown_error(): void
    {
        $error = $this->mapper->mapFromOpenRouter(['code' => 'unknown', 'message' => 'Something went wrong']);

        $this->assertEquals('upstream_error', $error->code);
        $this->assertTrue($error->retryable);
    }

    #[Test]
    public function maps_openrouter_error_without_code(): void
    {
        $error = $this->mapper->mapFromOpenRouter(['message' => 'Something went wrong']);

        $this->assertEquals('upstream_error', $error->code);
        $this->assertTrue($error->retryable);
    }

    #[Test]
    public function all_mapped_errors_have_user_safe_messages(): void
    {
        $codes = ['rate_limit', 'provider_unavailable', 'timeout', 'auth_error', 'stream_parse_error', 'connection_error', 'context_exceeded', 'upstream_error'];

        foreach ($codes as $code) {
            $error = $this->mapper->map($code);
            $this->assertNotEmpty($error->message, "Error code {$code} should have a non-empty message");
            // Messages should not contain technical details like stack traces
            $this->assertStringNotContainsString('Exception', $error->message);
            $this->assertStringNotContainsString('Stack trace', $error->message);
        }
    }
}
