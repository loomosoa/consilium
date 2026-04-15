<?php

namespace Tests\Unit;

use App\Exceptions\ApiKeyNotFoundException;
use App\Services\ApiKeyResolver;
use Illuminate\Session\SessionManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApiKeyResolverTest extends TestCase
{
    private ApiKeyResolver $resolver;

    private SessionManager $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = $this->app->make('session');
        $this->resolver = new ApiKeyResolver($this->session);
    }

    #[Test]
    public function env_key_has_priority_over_session(): void
    {
        config(['services.openrouter.key' => 'sk-env-key-1234567890']);
        $this->session->put('openrouter_api_key', 'sk-session-key-1234567890');

        $result = $this->resolver->resolve();

        $this->assertEquals('sk-env-key-1234567890', $result);
    }

    #[Test]
    public function fallback_to_session_when_no_env_key(): void
    {
        config(['services.openrouter.key' => null]);
        $this->session->put('openrouter_api_key', 'sk-session-key-1234567890');

        $result = $this->resolver->resolve();

        $this->assertEquals('sk-session-key-1234567890', $result);
    }

    #[Test]
    public function exception_when_no_key_in_any_source(): void
    {
        config(['services.openrouter.key' => null]);
        $this->session->forget('openrouter_api_key');

        $this->expectException(ApiKeyNotFoundException::class);
        $this->expectExceptionMessage('OpenRouter API key is not configured');

        $this->resolver->resolve();
    }

    #[Test]
    public function exception_when_env_key_is_empty_string(): void
    {
        config(['services.openrouter.key' => '']);
        $this->session->forget('openrouter_api_key');

        $this->expectException(ApiKeyNotFoundException::class);

        $this->resolver->resolve();
    }

    #[Test]
    public function has_key_returns_true_when_env_key_present(): void
    {
        config(['services.openrouter.key' => 'sk-env-key-1234567890']);
        $this->session->forget('openrouter_api_key');

        $this->assertTrue($this->resolver->hasKey());
    }

    #[Test]
    public function has_key_returns_true_when_session_key_present(): void
    {
        config(['services.openrouter.key' => null]);
        $this->session->put('openrouter_api_key', 'sk-session-key-1234567890');

        $this->assertTrue($this->resolver->hasKey());
    }

    #[Test]
    public function has_key_returns_false_when_no_key(): void
    {
        config(['services.openrouter.key' => null]);
        $this->session->forget('openrouter_api_key');

        $this->assertFalse($this->resolver->hasKey());
    }

    #[Test]
    public function requires_user_key_when_no_env_key(): void
    {
        config(['services.openrouter.key' => null]);

        $this->assertTrue($this->resolver->requiresUserKey());
    }

    #[Test]
    public function does_not_require_user_key_when_env_key_present(): void
    {
        config(['services.openrouter.key' => 'sk-env-key-1234567890']);

        $this->assertFalse($this->resolver->requiresUserKey());
    }

    #[Test]
    public function store_user_key_in_session(): void
    {
        $this->resolver->storeUserKey('sk-stored-key-1234567890');

        $this->assertEquals('sk-stored-key-1234567890', $this->session->get('openrouter_api_key'));
    }

    #[Test]
    public function remove_user_key_from_session(): void
    {
        $this->session->put('openrouter_api_key', 'sk-to-remove-1234567890');
        $this->resolver->removeUserKey();

        $this->assertNull($this->session->get('openrouter_api_key'));
    }

    #[Test]
    public function masked_key_shows_first_and_last_4_chars(): void
    {
        config(['services.openrouter.key' => 'sk-abcdefghij-1234567890XYZW']);

        $masked = $this->resolver->maskedKey();

        // sk-a(4) + •••••••••••••••••••(20) + XYZW(4) = 28 chars total
        $this->assertEquals(28, mb_strlen($masked));
        $this->assertStringStartsWith('sk-a', $masked);
        $this->assertStringEndsWith('XYZW', $masked);
        $this->assertStringContainsString('•', $masked);
    }

    #[Test]
    public function masked_key_returns_null_when_no_key(): void
    {
        config(['services.openrouter.key' => null]);
        $this->session->forget('openrouter_api_key');

        $this->assertNull($this->resolver->maskedKey());
    }

    #[Test]
    public function masked_key_short_key_fully_masked(): void
    {
        config(['services.openrouter.key' => 'sk-1234']);

        $masked = $this->resolver->maskedKey();

        // sk-1234 = 7 chars, all masked
        $this->assertEquals('•••••••', $masked);
    }

    #[Test]
    public function exception_message_does_not_leak_key(): void
    {
        config(['services.openrouter.key' => null]);
        $this->session->forget('openrouter_api_key');

        try {
            $this->resolver->resolve();
            $this->fail('Expected ApiKeyNotFoundException');
        } catch (ApiKeyNotFoundException $e) {
            // Exception message should not contain any key-like strings
            $this->assertStringNotContainsString('sk-', $e->getMessage());
            $this->assertStringNotContainsString('key:', $e->getMessage());
        }
    }
}
