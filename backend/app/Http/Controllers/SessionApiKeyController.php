<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpenRouterKeyRequest;
use App\Services\ApiKeyResolver;
use Illuminate\Http\JsonResponse;

class SessionApiKeyController extends Controller
{
    public function __construct(
        private ApiKeyResolver $apiKeyResolver,
    ) {}

    /**
     * POST /api/session/openrouter-key
     * Store the user-provided API key in the server session.
     * The key is never returned in the response.
     */
    public function store(StoreOpenRouterKeyRequest $request): JsonResponse
    {
        $this->apiKeyResolver->storeUserKey($request->validated('apiKey'));

        return response()->json([
            'stored' => true,
            'maskedKey' => $this->apiKeyResolver->maskedKey(),
        ]);
    }

    /**
     * DELETE /api/session/openrouter-key
     * Remove the user-provided API key from the server session.
     */
    public function destroy(): JsonResponse
    {
        $this->apiKeyResolver->removeUserKey();

        return response()->json([
            'deleted' => true,
        ]);
    }
}
