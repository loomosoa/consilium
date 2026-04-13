# Consilium — Design

## 0. Контекст и выбранный стек

Этот документ построен на основе `requirements.md`.

Выбранный стек:

- **Backend** — `PHP 8.3 + Laravel 12`
- **Frontend** — `Next.js 15 + TypeScript + Tailwind CSS`
- **Streaming** — `Server-Sent Events (SSE)` между frontend и backend, потоковый режим OpenRouter между backend и внешним API
- **Хранилище** — `PostgreSQL` для доменных данных, `Laravel Session` для временного хранения пользовательского OpenRouter API key

Причины выбора `Next.js`:

- удобно строить отзывчивый streaming UI;
- зрелая экосистема для клиентского состояния и e2e-тестов;
- хорошо сочетается с Tailwind и минималистичным UI;
- позволяет сделать mobile-first интерфейс, не теряя desktop-first сценарий на ширине `>= 1200px`.

Для ссылок на критерии приемки в этом документе используется нормализация вида `Requirements US-01.1`, где каждая строка EARS из `requirements.md` получает порядковый индекс внутри своего блока.

---

## 1. Архитектура

### 1.1. Общая структура системы

Система состоит из четырех слоев:

1. **Presentation Layer (`Next.js`)**

   - отображает центральный экран ввода;
   - выполняет первичный transition в рабочую область из четырех колонок;
   - открывает отдельный SSE-поток для каждой колонки;
   - управляет состоянием загрузки, ошибками, автоскроллом и повторным запросом.

2. **Application Layer (`Laravel`)**

   - принимает пользовательские команды;
   - создает workspace и независимые контексты колонок;
   - валидирует входные данные;
   - разрешает API key из `.env` или пользовательской сессии;
   - запускает запросы в OpenRouter и проксирует поток в SSE.

3. **Domain Layer (`Laravel Services`)**

   - содержит бизнес-логику workspace, column conversation, generation lifecycle;
   - гарантирует независимость колонок;
   - строит контекст сообщений для каждой модели;
   - переводит upstream-ошибки в доменные статусы.

4. **Infrastructure Layer**
   - `OpenRouterClient` для HTTP/stream-запросов;
   - `PostgreSQL` для хранения workspace/columns/messages/generations;
   - `Session Store` для безопасного краткоживущего хранения пользовательского API key.

### 1.2. Логическая схема взаимодействия

`Browser -> Next.js UI -> Laravel API -> OpenRouter API`

Дополнительно:

- `Next.js UI <- SSE stream <- Laravel API <- stream <- OpenRouter API`
- `Laravel API -> PostgreSQL`
- `Laravel API -> Session Store`

### 1.3. Архитектурные принципы

- **Single gateway**: все вызовы моделей идут только через OpenRouter.
- **Column isolation**: каждая колонка имеет собственный контекст, статус и поток.
- **Service Pattern**: бизнес-правила находятся в сервисах Laravel, контроллеры остаются тонкими.
- **Small methods**: методы узкие по ответственности — валидация, создание сущностей, запуск generation, маппинг ошибки, SSE-форматирование.
- **No shared assistant context**: контексты колонок не пересекаются.
- **Retry without context corruption**: незавершенный ассистентский ответ не включается в следующий контекст.

### 1.4. Основные сценарии

#### A. Первый запуск

1. Frontend вызывает `GET /api/config`.
2. Backend сообщает список 4 моделей и флаг `apiKeyRequired`.
3. Если ключ отсутствует и в `.env`, и в сессии, frontend показывает минималистичное модальное окно ввода ключа.
4. Пользователь видит только центральное поле ввода.

#### B. Первый промпт

1. Пользователь отправляет центральный промпт.
2. Frontend выполняет transition в `4-column workspace`.
3. Frontend вызывает `POST /api/workspaces`.
4. Backend:
   - создает `workspace`;
   - создает 4 `columns`;
   - копирует первый пользовательский промпт как первое сообщение в историю каждой колонки;
   - создает 4 `generations` — по одной на колонку.
5. Frontend открывает 4 независимых SSE-потока `GET /api/generations/{id}/stream`.
6. Каждая колонка обновляется независимо по мере получения токенов.

#### C. Продолжение разговора в колонке

1. Пользователь вводит сообщение внизу нужной колонки.
2. Frontend вызывает `POST /api/columns/{columnId}/messages`.
3. Backend добавляет новое пользовательское сообщение только в указанную колонку.
4. Backend создает новую `generation` только для этой колонки.
5. Frontend открывает SSE только для этой `generation`.

#### D. Ошибка и повторный запрос

1. Если upstream вернул ошибку или поток оборвался, колонка получает статус `error`.
2. Frontend показывает текст ошибки и кнопку `Повторить запрос`.
3. По нажатию вызывается `POST /api/generations/{generationId}/retry`.
4. Backend создает новую generation на основе последнего пользовательского сообщения и только подтвержденной истории колонки.

### 1.5. Решения по состоянию

- Источник истины по диалогам — backend.
- Frontend хранит только **оперативное UI-состояние**:
  - вид экрана (`landing` / `workspace`);
  - состояние потоков;
  - промежуточный отображаемый текст;
  - статус автоскролла.
- Backend хранит **доменное состояние**:
  - workspace;
  - колонка;
  - подтвержденные сообщения;
  - generation и ее итоговый статус.

### 1.6. Адаптивность UI

Хотя ключевой критерий приемки относится к desktop (`>= 1200px`), интерфейс проектируется mobile-first:

- `< 768px` — одна колонка с переключением между моделями табами;
- `768px - 1199px` — 2x2 grid;
- `>= 1200px` — 4 равные вертикальные колонки.

Desktop-конфигурация является обязательной и приоритетной для приемки.

---

## 2. Компоненты и интерфейсы

## 2.1. Frontend-модули (`Next.js`)

### `AppBootstrapService`

Ответственность:

- загрузка конфигурации приложения;
- определение необходимости ввода API key;
- инициализация клиентского store.

Вход:

- `GET /api/config`

Выход:

- `AppConfig`

### `WorkspaceStore`

Ответственность:

- хранение UI-состояния workspace;
- хранение массива колонок;
- обновление текста по токенам;
- переключение статусов `idle -> waiting -> streaming -> completed/error`.

Публичный интерфейс:

- `startWorkspace(payload)`
- `setColumnWaiting(columnId)`
- `appendToken(columnId, token)`
- `completeGeneration(columnId, messageId)`
- `failGeneration(columnId, error)`
- `retryGeneration(columnId)`

### `CentralPromptScreen`

Ответственность:

- отрисовка центрального поля ввода;
- отправка первого промпта;
- запуск UI transition.

### `WorkspaceGrid`

Ответственность:

- раскладка 4 колонок;
- сохранение равной ширины колонок на desktop;
- контейнер для автоскролла и событий стриминга.

### `ColumnPanel`

Ответственность:

- отображение заголовка модели;
- вывод ответов;
- состояние `loading/error/completed`;
- кнопка `Повторить запрос`;
- поле ввода follow-up сообщения.

### `StreamConnectionService`

Ответственность:

- открытие SSE-потока;
- разбор событий `meta`, `token`, `completed`, `error`, `heartbeat`;
- безопасное завершение и cleanup соединения.

### `ApiKeyModal`

Ответственность:

- ввод пользовательского OpenRouter API key;
- отправка ключа на backend;
- отсутствие хранения ключа в `localStorage`.

### `AutoScrollController`

Ответственность:

- прокрутка колонки вниз во время стриминга;
- поддержание видимости последних токенов.

## 2.2. Backend-модули (`Laravel`)

### Контроллеры

#### `ConfigController`

Методы:

- `GET /api/config`

Возвращает:

- список доступных 4 моделей;
- признак обязательности ручного ввода API key.

#### `SessionApiKeyController`

Методы:

- `POST /api/session/openrouter-key`
- `DELETE /api/session/openrouter-key`

Ответственность:

- валидация пользовательского ключа;
- безопасная запись в серверную сессию.

#### `WorkspaceController`

Методы:

- `POST /api/workspaces`
- `GET /api/workspaces/{workspaceId}`

Ответственность:

- создание workspace;
- инициализация 4 колонок;
- создание первых user messages и generations.

#### `ColumnMessageController`

Методы:

- `POST /api/columns/{columnId}/messages`

Ответственность:

- отправка follow-up сообщения в конкретную колонку;
- создание новой generation.

#### `GenerationStreamController`

Методы:

- `GET /api/generations/{generationId}/stream`

Ответственность:

- установка SSE-ответа;
- запуск потока из OpenRouter;
- трансляция токенов в клиент.

#### `GenerationRetryController`

Методы:

- `POST /api/generations/{generationId}/retry`

Ответственность:

- повторный запуск неуспешной generation без повреждения контекста.

### Сервисы

#### `WorkspaceService`

Ответственность:

- создание workspace и 4 колонок;
- выбор фиксированного набора моделей;
- фан-аут первого промпта на 4 контекста.

#### `ColumnConversationService`

Ответственность:

- сбор подтвержденной истории сообщений колонки;
- защита от межколоночного смешивания контекста.

#### `GenerationService`

Ответственность:

- управление жизненным циклом generation;
- перевод статусов;
- фиксация assistant message только после успешного завершения стрима.

#### `OpenRouterClient`

Ответственность:

- формирование upstream-запроса;
- работа в streaming-режиме;
- маппинг rate limit / timeout / provider unavailable.

#### `ApiKeyResolver`

Ответственность:

- приоритетный выбор ключа из `.env`;
- fallback на серверную сессию пользователя;
- отказ, если ключ отсутствует в обоих источниках.

#### `SseEventFactory`

Ответственность:

- формирование событий `event:` / `data:`;
- единый формат ответа для frontend.

#### `ErrorMapper`

Ответственность:

- преобразование технических ошибок в безопасные пользовательские сообщения.

## 2.3. Внешние API и контракты

### `GET /api/config`

Response:

- `apiKeyRequired: boolean`
- `models: ModelDefinition[4]`
- `layout: { desktopColumns: 4 }`

### `POST /api/session/openrouter-key`

Request:

- `apiKey: string`

Response:

- `stored: true`

### `POST /api/workspaces`

Request:

- `initialPrompt: string`

Response:

- `workspaceId: uuid`
- `columns: ColumnDto[4]`
- `generations: GenerationDto[4]`

Правила:

- один prompt копируется во все 4 контекста;
- каждая колонка получает собственный `generationId`.

### `POST /api/columns/{columnId}/messages`

Request:

- `prompt: string`

Response:

- `columnId: uuid`
- `generation: GenerationDto`

### `GET /api/generations/{generationId}/stream`

SSE events:

- `meta`
  - сведения о колонке, generation и модели
- `token`
  - `{ text: string, sequence: number }`
- `completed`
  - `{ generationId: string, assistantMessageId: string, finishReason: string }`
- `error`
  - `{ generationId: string, code: string, message: string, retryable: boolean }`
- `heartbeat`
  - служебное событие поддержания соединения

### `POST /api/generations/{generationId}/retry`

Request:

- пустое тело

Response:

- `generation: GenerationDto`

## 2.4. Границы ответственности

- **Frontend не знает OpenRouter API key** как постоянный клиентский секрет.
- **Backend не управляет layout-анимациями** — это зона frontend.
- **OpenRouterClient не принимает UI-решения** — только transport и provider mapping.
- **GenerationService не форматирует HTML** — только доменные данные и статусы.

---

## 3. Модели данных

## 3.1. Доменные сущности

### `ModelDefinition`

Поля:

- `code: string` — внутренний код, например `openai`, `anthropic`, `xai`, `google`
- `providerName: string`
- `displayName: string`
- `label: string` — строка вида `OpenAI · GPT-4o`
- `openRouterModelId: string`
- `order: integer`
- `enabled: boolean`

Валидация:

- `code` уникален в наборе из 4 моделей;
- `order` уникален и лежит в диапазоне `1..4`;
- `openRouterModelId` обязателен;
- в рабочем наборе всегда ровно 4 активных модели.

### `Workspace`

Поля:

- `id: uuid`
- `sessionId: string`
- `state: enum(initializing, active, completed, archived)`
- `initialPrompt: text`
- `createdAt: datetime`
- `updatedAt: datetime`

Валидация:

- `initialPrompt` — обязательный, trimmed, длина `1..8000` символов;
- `sessionId` обязателен;
- `state` принадлежит перечислению.

### `ColumnConversation`

Поля:

- `id: uuid`
- `workspaceId: uuid`
- `modelCode: string`
- `title: string`
- `position: smallint`
- `status: enum(idle, waiting, streaming, completed, error)`
- `lastGenerationId: uuid|null`
- `lastErrorCode: string|null`
- `lastErrorMessage: string|null`
- `createdAt: datetime`
- `updatedAt: datetime`

Валидация:

- `workspaceId` существует;
- `modelCode` принадлежит одному из 4 разрешенных кодов;
- `position` уникальна в пределах workspace и лежит в диапазоне `1..4`.

### `Message`

Поля:

- `id: uuid`
- `columnId: uuid`
- `role: enum(system, user, assistant)`
- `content: text`
- `sequence: integer`
- `generationId: uuid|null`
- `createdAt: datetime`

Валидация:

- `content` — обязательный, trimmed, длина `1..32000` символов;
- `role` принадлежит перечислению;
- `sequence` уникален в пределах колонки и монотонно возрастает.

### `Generation`

Поля:

- `id: uuid`
- `columnId: uuid`
- `userMessageId: uuid`
- `status: enum(pending, streaming, completed, error, cancelled)`
- `partialOutput: longtext|null`
- `errorCode: string|null`
- `errorMessage: string|null`
- `retryable: boolean`
- `startedAt: datetime|null`
- `completedAt: datetime|null`
- `createdAt: datetime`

Валидация:

- `userMessageId` обязан ссылаться на `Message.role = user`;
- одновременно у одной колонки не может быть больше одной active generation в статусах `pending|streaming`;
- `partialOutput` не включается в подтвержденный контекст, пока `status != completed`.

### `SessionApiKeyPayload`

Поля:

- `source: enum(env, session)`
- `apiKeyMasked: string`
- `storedAt: datetime`

Валидация:

- пользовательский ключ не сохраняется в `localStorage`;
- ключ из UI хранится только в серверной сессии;
- в логах и ответах наружу выводится только маскированное значение.

## 3.2. DTO для frontend

### `AppConfig`

- `apiKeyRequired: boolean`
- `models: ModelDefinition[]`

### `ColumnDto`

- `id: string`
- `workspaceId: string`
- `label: string`
- `providerName: string`
- `displayName: string`
- `position: number`
- `status: string`

### `GenerationDto`

- `id: string`
- `columnId: string`
- `status: string`
- `retryable: boolean`

## 3.3. Инварианты данных

- В каждом `workspace` всегда **ровно 4 колонки**.
- Внутри одного `workspace` каждая модель используется **ровно один раз**.
- Первое пользовательское сообщение присутствует в истории **каждой** колонки.
- Сообщения ассистента попадают в подтвержденную историю только после события `completed`.
- Ошибка одной generation не меняет статус и историю других колонок.

---

## 4. Свойства корректности

Ниже приведены тестируемые свойства для каждого критерия приемки.

### US-01. Центральный ввод промпта и первичный переход интерфейса

- **Property 01**

  - Для любого первого рендера клиентской сессии, должно выполняться отображение ровно одного центрального поля ввода промпта и отсутствие видимой 4-колоночной рабочей области.
  - Validates: Requirements US-01.1

- **Property 02**

  - Для любой успешной отправки первого промпта, должно выполняться скрытие центрального поля ввода и отображение рабочей области из четырех колонок одинаковой ширины.
  - Validates: Requirements US-01.2

- **Property 03**

  - Для любого первого промпта workspace, должно выполняться создание четырех независимых generation-запросов с одинаковым пользовательским текстом, отправленных ко всем четырем моделям через OpenRouter.
  - Validates: Requirements US-01.3

- **Property 04**
  - Для любой колонки, созданной из первого промпта, должно выполняться, что первым сообщением в ее истории является исходный пользовательский промпт workspace.
  - Validates: Requirements US-01.4

### US-02. Разделение экрана на колонки и потоковый вывод

- **Property 05**

  - Для любой отображаемой колонки, должно выполняться наличие в заголовке серого текстового label формата `Провайдер · Модель`.
  - Validates: Requirements US-02.1

- **Property 06**
  - Для любого SSE-события `token`, полученного для конкретной generation, должно выполняться немедленное дописывание текста только в соответствующую колонку без ожидания завершения всей генерации.
  - Validates: Requirements US-02.2

### US-03. Индикация состояния загрузки и обработка ошибок

- **Property 07**

  - Для любой колонки в состоянии ожидания первого токена, должно выполняться отображение анимированного индикатора загрузки.
  - Validates: Requirements US-03.1

- **Property 08**

  - Для любой колонки, получившей первый токен ответа, должно выполняться скрытие индикатора загрузки и отображение потокового текста ответа в этой же колонке.
  - Validates: Requirements US-03.2

- **Property 09**

  - Для любой generation, завершившейся ошибкой OpenRouter, должно выполняться прекращение состояния загрузки, отображение пользовательского текста ошибки и наличие активной кнопки `Повторить запрос`.
  - Validates: Requirements US-03.3

- **Property 10**
  - Для любой ошибки в одной колонке, должно выполняться сохранение неизменной работоспособности, загрузки и отображения данных в остальных трех колонках.
  - Validates: Requirements US-03.4

### US-04. Продолжение диалога внутри конкретной колонки

- **Property 11**

  - Для любой рабочей колонки, должно выполняться наличие независимого поля ввода промпта в нижней части колонки.
  - Validates: Requirements US-04.1

- **Property 12**

  - Для любого follow-up сообщения, отправленного из конкретной колонки, должно выполняться создание generation только для выбранной модели без сетевых запросов в остальные три колонки.
  - Validates: Requirements US-04.2

- **Property 13**
  - Для любого нового запроса внутри колонки, должно выполняться включение в upstream payload всей подтвержденной предыдущей переписки именно этой колонки и отсутствие сообщений из других колонок.
  - Validates: Requirements US-04.3

### US-05. Общий UI/UX дизайн

- **Property 14**

  - Для любого основного экрана приложения, должно выполняться использование light theme design tokens, большого свободного пространства и отсутствие обязательных для сценария элементов вне центрального промпта, колонок, индикаторов и полей ввода.
  - Validates: Requirements US-05.1

- **Property 15**

  - Для любого viewport с шириной `>= 1200px`, должно выполняться отображение 4-колоночной сетки, где каждая колонка занимает `25% ± допустимая погрешность layout engine` ширины контейнера.
  - Validates: Requirements US-05.2

- **Property 16**
  - Для любой колонки в состоянии streaming, должно выполняться удержание видимой области у нижней границы потока так, чтобы последний выведенный токен оставался видимым пользователю.
  - Validates: Requirements US-05.3

### US-06. Технические и системные требования

- **Property 17**

  - Для любого запроса к модели, инициированного приложением, должно выполняться использование OpenRouter как единственного внешнего шлюза к провайдерам моделей.
  - Validates: Requirements US-06.1

- **Property 18**

  - Для любой generation, отображаемой в UI в режиме реального времени, должно выполняться получение и доставка ответа в frontend через потоковую передачу SSE-событий.
  - Validates: Requirements US-06.2

- **Property 19**

  - Для любой активной колонки с незавершенным сетевым запросом, должно выполняться, что интерфейс и сетевые операции других колонок остаются доступными и не блокируются.
  - Validates: Requirements US-06.3

- **Property 20**
  - Для любого запуска приложения без OpenRouter API key в `.env`, должно выполняться отображение пользователю интерфейса ручного ввода ключа до первой отправки промпта.
  - Validates: Requirements US-06.4

---

## 5. Обработка ошибок

## 5.1. Категории ошибок

### Ошибки конфигурации

- отсутствует API key в `.env` и в сессии;
- не настроен список из 4 моделей;
- некорректный `openRouterModelId`.

Реакция:

- показать модальное окно ввода ключа или экран конфигурационной ошибки;
- запретить запуск generation до исправления.

### Ошибки валидации

- пустой prompt;
- слишком длинный prompt;
- несуществующий `columnId` или `generationId`;
- запрос к колонке из чужой сессии;
- повторный запрос при уже активной generation в этой колонке.

Реакция:

- вернуть `422` или `404/403`;
- не менять состояние других колонок;
- показать локальную ошибку в соответствующем UI-контексте.

### Ошибки upstream OpenRouter

- `429 rate limit`;
- `5xx provider unavailable`;
- сетевой timeout;
- обрыв streaming-соединения;
- некорректный формат потокового события.

Реакция:

- завершить только проблемную generation статусом `error`;
- сохранить `errorCode`, `errorMessage`, `retryable`;
- оставить частичный текст как `partialOutput` для отображения, но не включать его в контекст следующего запроса;
- показать `Повторить запрос`.

## 5.2. Стратегия изоляции ошибок

- Каждая колонка имеет собственный `generationId`, собственное соединение и собственный статус.
- Backend не использует общий транзакционный контур для четырех потоков ответа.
- Ошибка, timeout или retry в одной колонке не вызывает rollback других колонок.
- Frontend подписывается на 4 разных потока и хранит состояние по ключу `columnId`.

## 5.3. Политика повторного запроса

При retry:

- повторяется только последняя неуспешная операция конкретной колонки;
- используется последний user message этой колонки;
- в контекст включаются только подтвержденные сообщения;
- незавершенный assistant output из предыдущей failed generation в контекст не попадает.

## 5.4. Безопасность

- API key не возвращается клиенту в открытом виде после сохранения.
- API key не пишется в application logs.
- Все пользовательские тексты экранируются на frontend при рендеринге.
- Доступ к `workspace`, `column`, `generation` ограничивается текущей пользовательской сессией.

---

## 6. Стратегия тестирования

## 6.1. Unit tests

### Backend

Покрыть:

- `ApiKeyResolver`
- `WorkspaceService`
- `ColumnConversationService`
- `GenerationService`
- `ErrorMapper`
- `SseEventFactory`
- валидаторы request DTO

Проверки:

- корректный выбор ключа из `.env` или сессии;
- создание ровно 4 колонок;
- запрет двух одновременных active generation в одной колонке;
- исключение `partialOutput` из контекста retry;
- корректный маппинг ошибок OpenRouter.

### Frontend

Покрыть:

- `WorkspaceStore`
- `StreamConnectionService`
- `AutoScrollController`
- логику transition `landing -> workspace`

Проверки:

- токены добавляются только в нужную колонку;
- loader исчезает на первом токене;
- ошибка выставляет retryable UI-state;
- автоскролл удерживает последний токен в зоне видимости.

## 6.2. Feature / Integration tests (`Laravel`)

Покрыть:

- `GET /api/config`
- `POST /api/session/openrouter-key`
- `POST /api/workspaces`
- `POST /api/columns/{columnId}/messages`
- `GET /api/generations/{generationId}/stream`
- `POST /api/generations/{generationId}/retry`

Проверки:

- без ключа backend требует ручной ввод;
- первый prompt создает workspace, 4 columns, 4 generations;
- follow-up работает только для одной колонки;
- при ошибке upstream остальные колонки не затрагиваются;
- completed generation создает assistant message;
- failed generation не создает подтвержденный assistant message.

## 6.3. Component tests (`Next.js`)

Покрыть:

- `CentralPromptScreen`
- `WorkspaceGrid`
- `ColumnPanel`
- `ApiKeyModal`

Проверки:

- на старте виден только центральный prompt;
- после submit отображаются 4 колонки;
- заголовки колонок показывают `Провайдер · Модель`;
- поле ввода существует внизу каждой колонки;
- кнопка `Повторить запрос` появляется только при ошибке.

## 6.4. E2E tests (`Playwright`)

Сценарии:

1. **First prompt fan-out**

   - открыть приложение;
   - ввести первый prompt;
   - убедиться в transition и появлении 4 колонок;
   - убедиться, что каждая колонка получила собственный поток.

2. **Streaming UX**

   - замокать постепенный поток токенов;
   - проверить появление loader;
   - проверить исчезновение loader на первом токене;
   - проверить рост текста в реальном времени.

3. **Column isolation**

   - сломать один upstream stream;
   - убедиться, что три другие колонки продолжают отвечать.

4. **Follow-up in single column**

   - отправить второй prompt в одну колонку;
   - проверить, что обновляется только она.

5. **Retry flow**

   - получить ошибку;
   - нажать `Повторить запрос`;
   - проверить запуск новой generation и успешное завершение.

6. **Desktop layout**
   - установить viewport `>= 1200px`;
   - проверить 4 равные колонки.

## 6.5. Contract tests

Нужны фикстуры streaming-ответов OpenRouter:

- нормальный поток токенов;
- пустой поток с ошибкой;
- rate limit;
- разрыв соединения после частичного ответа.

Цель:

- гарантировать совместимость `OpenRouterClient` и `SseEventFactory` с реальным форматом upstream.

## 6.6. Нефункциональные тесты

- **Latency smoke test** — UI остается интерактивным при 4 одновременных stream-соединениях.
- **Session scoping test** — пользовательская сессия не может получить чужой workspace.
- **Long response test** — автоскролл и рендер корректны на длинных ответах.

---

## 7. Резюме проектного решения

Предлагаемая архитектура строится вокруг `Laravel` как доменного оркестратора и `Next.js` как streaming UI-клиента.

Ключевые свойства решения:

- первый prompt fan-out на 4 модели;
- независимый контекст каждой колонки;
- real-time streaming через SSE;
- безопасная работа с OpenRouter API key;
- локальная обработка ошибок без влияния на остальные колонки;
- тестируемость через четкие доменные модели, API-контракты и свойства корректности.
