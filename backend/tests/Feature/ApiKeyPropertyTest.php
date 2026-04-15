<?php

namespace Tests\Feature;

use App\Services\ApiKeyResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiKeyPropertyTest extends TestCase
{
    /**
     * Prop. 20: без ключа в .env система требует ручный ввод до первой отправки промпта.
     */
    #[Test]
    public function system_requires_user_key_input_when_no_env_key(): void
    {
        config(['services.openrouter.key' => null]);
        session()->forget('openrouter_api_key');

        $resolver = $this->app->make(ApiKeyResolver::class);

        // System signals that user must provide a key
        $this->assertTrue($resolver->requiresUserKey());

        // Attempting to resolve without any key throws exception
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenRouter API key is not configured');

        $resolver->resolve();
    }

    #[Test]
    public function system_allows_operations_after_user_provides_key(): void
    {
        config(['services.openrouter.key' => null]);
        session()->forget('openrouter_api_key');

        $resolver = $this->app->make(ApiKeyResolver::class);

        // Before user provides key — cannot resolve
        $this->assertFalse($resolver->hasKey());

        // User provides key via session
        $this->postJson('/api/session/openrouter-key', [
            'apiKey' => 'sk-user-provided-key-1234567890ab',
        ])->assertOk();

        // After user provides key — can resolve
        $this->assertTrue($resolver->hasKey());
        $key = $resolver->resolve();
        $this->assertEquals('sk-user-provided-key-1234567890ab', $key);
    }

    #[Test]
    public function env_key_bypasses_need_for_user_input(): void
    {
        config(['services.openrouter.key' => 'sk-env-configured-key-12345678']);

        $resolver = $this->app->make(ApiKeyResolver::class);

        // No user input needed
        $this->assertFalse($resolver->requiresUserKey());
        $this->assertTrue($resolver->hasKey());

        // Env key is used even if session has a different key
        session(['openrouter_api_key' => 'sk-session-key-1234567890ab']);
        $this->assertEquals('sk-env-configured-key-12345678', $resolver->resolve());
    }

    #[Test]
    public function deleting_session_key_restores_requirement_if_no_env_key(): void
    {
        config(['services.openrouter.key' => null]);

        $resolver = $this->app->make(ApiKeyResolver::class);

        // User provides key
        $this->postJson('/api/session/openrouter-key', [
            'apiKey' => 'sk-user-key-1234567890abcdef',
        ])->assertOk();

        $this->assertTrue($resolver->hasKey());

        // User deletes key
        $this->deleteJson('/api/session/openrouter-key')->assertOk();

        // System requires key again
        $this->assertTrue($resolver->requiresUserKey());
        $this->assertFalse($resolver->hasKey());
    }
}
