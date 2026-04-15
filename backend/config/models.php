<?php

return [
    'definitions' => [
        [
            'code' => 'xai',
            'providerName' => 'xAI',
            'displayName' => 'Grok 4.20',
            'label' => 'xAI · Grok 4.20',
            'openRouterModelId' => 'x-ai/grok-4.20',
            'contextWindow' => 131072,
            'order' => 1,
            'enabled' => true,
        ],
        [
            'code' => 'google',
            'providerName' => 'Google',
            'displayName' => 'Gemini 3.1 Pro',
            'label' => 'Google · Gemini 3.1 Pro',
            'openRouterModelId' => 'google/gemini-3.1-pro-preview',
            'contextWindow' => 2000000,
            'order' => 2,
            'enabled' => true,
        ],
        [
            'code' => 'zai',
            'providerName' => 'Z.ai',
            'displayName' => 'GLM-5.1',
            'label' => 'Z.ai · GLM-5.1',
            'openRouterModelId' => 'z-ai/glm-5.1',
            'contextWindow' => 128000,
            'order' => 3,
            'enabled' => true,
        ],
        [
            'code' => 'openai',
            'providerName' => 'OpenAI',
            'displayName' => 'GPT-5.2',
            'label' => 'OpenAI · GPT-5.2',
            'openRouterModelId' => 'openai/gpt-5.2',
            'contextWindow' => 256000,
            'order' => 4,
            'enabled' => true,
        ],
    ],
];
