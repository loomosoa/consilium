<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SessionApiKeyControllerTest extends TestCase
{
    #[Test]
    public function store_api_key_in_session(): void
    {
        config(['services.openrouter.key' => null]);

        $response = $this->postJson('/api/session/openrouter-key', [
            'apiKey' => 'sk-valid-key-1234567890abcdef',
        ]);

        $response->assertOk();
        $response->assertJson(['stored' => true]);
        $response->assertJsonStructure(['stored', 'maskedKey']);

        // Key should NOT be returned in plain text
        $response->assertDontSeeText('sk-valid-key-1234567890abcdef');
    }

    #[Test]
    public function store_validates_key_format(): void
    {
        $response = $this->postJson('/api/session/openrouter-key', [
            'apiKey' => 'invalid-key-format',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('apiKey');
    }

    #[Test]
    public function store_validates_key_required(): void
    {
        $response = $this->postJson('/api/session/openrouter-key', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('apiKey');
    }

    #[Test]
    public function store_validates_key_min_length(): void
    {
        $response = $this->postJson('/api/session/openrouter-key', [
            'apiKey' => 'sk-short',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function delete_api_key_from_session(): void
    {
        session(['openrouter_api_key' => 'sk-to-delete-1234567890ab']);

        $response = $this->deleteJson('/api/session/openrouter-key');

        $response->assertOk();
        $response->assertJson(['deleted' => true]);
        $this->assertNull(session('openrouter_api_key'));
    }

    #[Test]
    public function key_not_returned_in_plain_text(): void
    {
        config(['services.openrouter.key' => null]);

        $response = $this->postJson('/api/session/openrouter-key', [
            'apiKey' => 'sk-supersecret-key-1234567890xyz',
        ]);

        $content = $response->getContent();

        $this->assertStringNotContainsString('sk-supersecret-key-1234567890xyz', $content);
        $this->assertStringContainsString('maskedKey', $content);
    }

    #[Test]
    public function masked_key_format_in_response(): void
    {
        config(['services.openrouter.key' => null]);

        $response = $this->postJson('/api/session/openrouter-key', [
            'apiKey' => 'sk-abcdefghij-1234567890XYZW',
        ]);

        $response->assertOk();
        $maskedKey = $response->json('maskedKey');

        $this->assertNotEquals('sk-abcdefghij-1234567890XYZW', $maskedKey);
        $this->assertStringContainsString('•', $maskedKey);
    }
}
