<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ApiKeyResolver;
use App\Services\ModelDefinitionService;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function __construct(
        private ModelDefinitionService $modelDefinitionService,
        private ApiKeyResolver $apiKeyResolver,
    ) {}

    /**
     * GET /api/config
     *
     * Returns application configuration for the frontend:
     * - models: list of active model definitions
     * - apiKeyRequired: whether the user must manually provide an API key
     * - layout: desktop layout configuration
     */
    public function show(): JsonResponse
    {
        $models = $this->modelDefinitionService->active();

        return response()->json([
            'models' => array_map(fn ($m) => [
                'code' => $m->code,
                'providerName' => $m->providerName,
                'displayName' => $m->displayName,
                'label' => $m->label,
                'openRouterModelId' => $m->openRouterModelId,
                'contextWindow' => $m->contextWindow,
                'order' => $m->order,
            ], $models),
            'apiKeyRequired' => $this->apiKeyResolver->requiresUserKey(),
            'layout' => [
                'desktopColumns' => count($models),
            ],
        ]);
    }
}
