# Model Configuration

## Структура

Конфигурация моделей разделена на две категории:

### Premium Models (`config('models.premium')`)

Топовые платные модели для основного функционала:

-   **xAI Grok 4.20** — 131K context
-   **Google Gemini 3.1 Pro** — 2M context
-   **Z.ai GLM-5.1** — 128K context
-   **OpenAI GPT-5.2** — 256K context

### Free Models (`config('models.free')`)

Бесплатные модели для тестирования и демо:

-   **NVIDIA Nemotron 3 Super 120B** — 32K context
-   **Arcee AI Trinity Large** — 8K context
-   **Z.ai GLM-4.5 Air** — 128K context
-   **OpenAI GPT OSS 120B** — 8K context

## Использование

### ModelDefinitionService

```php
$service = new ModelDefinitionService();

// Получить все модели (premium + free)
$all = $service->all(); // 8 моделей

// Получить только premium
$premium = $service->premium(); // 4 модели

// Получить только free
$free = $service->free(); // 4 модели

// Active по умолчанию = free
$active = $service->active(); // 4 free модели

// Найти модель по коду (ищет во всех категориях)
$model = $service->findByCode('xai'); // premium модель
$model = $service->findByCode('nvidia'); // free модель

// Минимальное context window среди active (free)
$minContext = $service->smallestContextWindow(); // 8192
```

## Формат записи

```php
[
    'code' => 'unique-code',           // Уникальный код модели
    'providerName' => 'Provider',      // Название провайдера
    'displayName' => 'Model Name',     // Отображаемое имя модели
    'label' => 'Provider · Model',     // Полная метка для UI
    'openRouterModelId' => 'id',       // ID для OpenRouter API
    'contextWindow' => 128000,         // Размер context window
    'order' => 1,                      // Порядок сортировки (1-4)
    'enabled' => true,                 // Включена ли модель
]
```

## Правила

1. **Уникальность кодов**: Все коды моделей должны быть уникальны в рамках всей конфигурации
2. **Order**: В каждой категории order от 1 до 4
3. **OpenRouter ID**: Free модели должны иметь суффикс `:free`
4. **Enabled**: Только enabled модели возвращаются сервисом

## Добавление новой модели

1. Добавить в соответствующую категорию (`premium` или `free`)
2. Указать уникальный `code`
3. Установить `order` (1-4 в рамках категории)
4. Указать корректный `openRouterModelId`
5. Запустить тесты: `php artisan test --filter=ModelDefinitionServiceTest`

## Тесты

Тесты проверяют:

-   ✅ Ровно 4 модели в каждой категории
-   ✅ Уникальность кодов
-   ✅ Уникальность order в каждой категории
-   ✅ Сортировку по order
-   ✅ Наличие OpenRouter ID
-   ✅ Суффикс `:free` для бесплатных моделей
-   ✅ Поиск моделей по коду
