<?php

namespace App\Services;

use Illuminate\Session\SessionManager;

class ApiKeyResolver
{
    private const SESSION_KEY = 'openrouter_api_key';

    private const ENV_KEY = 'OPENROUTER_API_KEY';

    public function __construct(
        private SessionManager $session,
    ) {}

    /**
     * Resolve the OpenRouter API key.
     *
     * Priority: .env > session > exception.
     *
     * @throws \RuntimeException when no key is available in any source
     */
    public function resolve(): string
    {
        $envKey = config('services.openrouter.key')
            ?? env(self::ENV_KEY);

        if ($envKey !== null && $envKey !== '') {
            return $envKey;
        }

        $sessionKey = $this->session->get(self::SESSION_KEY);

        if ($sessionKey !== null && $sessionKey !== '') {
            return $sessionKey;
        }

        throw new \RuntimeException('OpenRouter API key is not configured. Set OPENROUTER_API_KEY in .env or provide it via the session.');
    }

    /**
     * Check whether an API key is available from any source.
     */
    public function hasKey(): bool
    {
        try {
            $this->resolve();

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    /**
     * Whether the system requires the user to manually input an API key.
     * True when no key is present in .env.
     */
    public function requiresUserKey(): bool
    {
        $envKey = config('services.openrouter.key')
            ?? env(self::ENV_KEY);

        return $envKey === null || $envKey === '';
    }

    /**
     * Store the user-provided API key in the session.
     * The key is never logged or returned in responses.
     */
    public function storeUserKey(string $apiKey): void
    {
        $this->session->put(self::SESSION_KEY, $apiKey);
    }

    /**
     * Remove the user-provided API key from the session.
     */
    public function removeUserKey(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * Return a masked version of the key for safe display.
     * Shows first 4 and last 4 characters, masks the rest.
     */
    public function maskedKey(): ?string
    {
        try {
            $key = $this->resolve();
        } catch (\RuntimeException) {
            return null;
        }

        $length = mb_strlen($key);

        if ($length <= 8) {
            return str_repeat('•', $length);
        }

        return mb_substr($key, 0, 4).str_repeat('•', $length - 8).mb_substr($key, -4);
    }
}
