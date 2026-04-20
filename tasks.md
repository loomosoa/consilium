# Consilium — Implementation Plan

---

## 1. Scaffolding & инфраструктура проекта

- [x] 1.1 Инициализация Laravel 12 проекта с PHP 8.3, установка Octane (FrankenPHP/Swoole), Sanctum (Req.: US-06.1, US-06.3)
- [x] 1.2 Инициализация Vue 3 + Vite проекта с TypeScript, Tailwind CSS, Pinia, markdown-it (Req.: US-05.1, US-06.2)
- [x] 1.3 Настройка PostgreSQL: подключение, `.env.example`, конфиг database (Req.: US-06.1)
- [x] 1.4 Настройка Sanctum SPA: CORS, cookie domain, `sanctum/csrf-cookie` endpoint (Req.: US-06.4)
- [x] 1.5 Настройка линтеров и форматтеров: PHP CS Fixer / Pint, ESLint, Prettier
- [x] 1.6 Unit-тесты
  - Sanctum CSRF-cookie доступен
  - PostgreSQL connection health check
- [x] 1.7 Чекпоинт: тесты, компиляция (Laravel + Vue 3/Vite), линтер

---

## 2. Модели данных и миграции

- [x] 2.1 Миграция и Eloquent-модель `Workspace` (Req.: US-01.3, US-01.4)
- [x] 2.2 Миграция и Eloquent-модель `ColumnConversation` с UUID PK, unique `(workspace_id, position)` (Req.: US-02.1, US-04.1)
- [x] 2.3 Миграция и Eloquent-модель `Message` с unique `(column_id, sequence)` (Req.: US-04.3)
- [x] 2.4 Миграция и Eloquent-модель `Generation` с constraint на одну active generation per column (Req.: US-07.2, US-03.3)
- [x] 2.5 Конфигурация `ModelDefinition`: фиксированный набор моделей (premium + free) в config-файле (Req.: US-02.1)
- [x] 2.6 Unit-тесты
  - Workspace создаётся с обязательными полями, валидация `initialPrompt` (1..100000)
  - ColumnConversation: position уникальна в workspace, диапазон 1..4
  - Message: sequence уникален в column, role принадлежит enum
  - Generation: userMessageId ссылается на user message; constraint — не более одной active generation (pending|streaming) на колонку
  - ModelDefinition: ровно 4 модели, code и order уникальны
- [x] 2.7 Property-тесты
  - Prop. 03: создание workspace порождает ровно 4 колонки с разными моделями
  - Prop. 04: первое сообщение каждой колонки — исходный промпт workspace
- [x] 2.8 Чекпоинт: миграции, тесты, компиляция, линтер

---

## 3. API Key Management

- [x] 3.1 `ApiKeyResolver` сервис: приоритет `.env`, fallback на сессию, отказ при отсутствии (Req.: US-06.4)
- [x] 3.2 `SessionApiKeyController`: `POST /api/session/openrouter-key`, `DELETE /api/session/openrouter-key` — валидация, запись в серверную сессию, маскирование (Req.: US-06.4)
- [x] 3.3 Unit-тесты
  - ApiKeyResolver: ключ из `.env` приоритетнее сессии
  - ApiKeyResolver: fallback на сессию при отсутствии `.env`
  - ApiKeyResolver: исключение при отсутствии ключа в обоих источниках
  - SessionApiKeyController: валидация формата ключа
  - SessionApiKeyController: удаление ключа из серверной сессии через `DELETE /api/session/openrouter-key`
  - Ключ не возвращается клиенту в открытом виде, не пишется в логи
- [x] 3.4 Property-тесты
  - Prop. 20: без ключа в `.env` система требует ручной ввод до первой отправки промпта
- [x] 3.5 Чекпоинт: тесты, компиляция, линтер

---

## 4. Config & Bootstrap

- [x] 4.1 `ConfigController`: `GET /api/config` — список моделей, флаг `apiKeyRequired`, `layout.desktopColumns = 4` (Req.: US-06.4, US-02.1)
- [x] 4.2 Frontend `AppBootstrapService`: CSRF init, загрузка конфига, определение необходимости ввода API key (Req.: US-06.4)
- [x] 4.3 Frontend `ApiKeyModal`: ввод ключа, отправка на backend, без localStorage (Req.: US-06.4)
- [x] 4.4 Unit-тесты
  - ConfigController: возвращает 4 модели, корректный `apiKeyRequired` и `layout.desktopColumns = 4`
  - AppBootstrapService: правильно инициализирует store из конфига
  - ApiKeyModal: не сохраняет ключ в localStorage
- [x] 4.5 Property-тесты
  - Prop. 20: при отсутствии ключа отображается интерфейс ручного ввода
- [x] 4.6 Чекпоинт: тесты, компиляция, линтер
- [x] Улучшения: CORS для Sanctum, loading spinner, retry button, config store reset

---

## 5. Workspace & первый промпт (Backend)

- [x] 5.1 `WorkspaceService`: создание workspace, 4 колонок, копирование промпта как первого user message в каждую колонку, создание 4 generation, валидация initial prompt против `contextWindow` наименьшей модели (Req.: US-01.3, US-01.4)
- [x] 5.2 `WorkspaceController`: `POST /api/workspaces`, `GET /api/workspaces/{workspaceId}` — валидация, вызов сервиса, DTO-ответ (Req.: US-01.3)
- [x] 5.3 Unit-тесты
  - WorkspaceService: создаёт ровно 4 колонки с уникальными моделями
  - WorkspaceService: первое user message одинаково во всех 4 колонках
  - WorkspaceService: создаёт 4 generation в статусе `pending`
  - WorkspaceController: валидация — пустой/слишком длинный промпт возвращает 422
  - WorkspaceController: промпт, не помещающийся в `contextWindow` наименьшей модели, отклоняется валидацией
  - WorkspaceController: корректный DTO-ответ с workspaceId, columns[4], generations[4]
- [x] 5.4 Property-тесты
  - Prop. 03: для любого промпта — 4 generation с одинаковым текстом, разными моделями
  - Prop. 04: первое сообщение каждой колонки === initialPrompt
- [x] 5.5 Чекпоинт: тесты, компиляция, линтер

---

## 6. Column Conversation & follow-up (Backend)

- [x] 6.1 `ColumnConversationService`: сбор подтверждённой истории, контроль длины по contextWindow модели, защита от межколоночного смешивания (Req.: US-04.3)
- [x] 6.2 `ColumnMessageController`: `POST /api/columns/{columnId}/messages` — добавление follow-up, создание generation (Req.: US-04.1, US-04.2)
- [x] 6.3 Unit-тесты
  - ColumnConversationService: история содержит только подтверждённые сообщения данной колонки
  - ColumnConversationService: partialOutput отменённых/ошибочных generation не включается в контекст
  - ColumnConversationService: автоматическое отсечение старых сообщений при превышении contextWindow
  - ColumnConversationService: при переполнении contextWindow старые сообщения отсекаются без `422` пользователю
  - ColumnMessageController: follow-up создаёт generation только для указанной колонки
  - ColumnMessageController: запрет follow-up при наличии active generation (pending|streaming)
- [x] 6.4 Property-тесты
  - Prop. 12: follow-up создаёт generation только для одной модели
  - Prop. 13: upstream payload содержит только историю данной колонки, без сообщений из других
- [x] 6.5 Чекпоинт: тесты, компиляция, линтер

---

## 7. OpenRouter Client

- [x] 7.1 `OpenRouterClient`: формирование upstream-запроса, streaming-режим, маппинг ошибок (rate limit, timeout, provider unavailable), прерывание по cancel (Req.: US-06.1, US-06.2)
- [x] 7.2 `ErrorMapper`: преобразование технических ошибок в безопасные пользовательские сообщения (Req.: US-03.3)
- [x] 7.3 `ApiKeyResolver::validateKey()`: опциональная валидация ключа через тестовый запрос к OpenRouter API (улучшение UX — пользователь сразу узнает о неверном ключе)
- [x] 7.4 Unit-тесты
  - OpenRouterClient: формирует корректный запрос с моделью и контекстом
  - OpenRouterClient: парсит нормальный поток токенов
  - OpenRouterClient: маппит 429 rate limit → retryable error
  - OpenRouterClient: маппит 5xx → retryable error
  - OpenRouterClient: маппит timeout → retryable error
  - OpenRouterClient: прерывает HTTP-соединение по cancel
  - ErrorMapper: преобразует коды ошибок в user-safe сообщения
  - ApiKeyResolver::validateKey(): проверяет валидность ключа через OpenRouter
- [x] 7.5 Property-тесты
  - Prop. 17: любой запрос к модели проходит только через OpenRouter
- [x] 7.6 Contract-тесты (фикстуры OpenRouter)
  - Нормальный поток токенов
  - Пустой поток с ошибкой
  - Rate limit
  - Разрыв соединения после частичного ответа
  - Cancel после частичного ответа
- [x] 7.7 Чекпоинт: тесты, компиляция, линтер

---

## 8. Generation Lifecycle & SSE Streaming (Backend)

- [x] 8.1 `GenerationService`: управление lifecycle (pending → streaming → completed/error/cancelled), синхронизация статуса `ColumnConversation`, фиксация assistant message только при completed, обработка cancel (Req.: US-02.2, US-03.3, US-07.2)
- [x] 8.2 `SseEventFactory`: формирование событий `meta`, `token`, `completed`, `error`, `cancelled`, `heartbeat` (Req.: US-06.2)
- [x] 8.3 `GenerationStreamController`: `GET /api/generations/{generationId}/stream` — SSE-ответ, запуск потока из OpenRouter, трансляция токенов, heartbeat каждые 15 секунд для длинных генераций, обработка `Connection Closed` с немедленным прерыванием upstream (Req.: US-06.2, US-02.2)
- [x] 8.4 Unit-тесты
  - GenerationService: pending → streaming → completed создаёт Message(role=assistant)
  - GenerationService: pending → streaming → error не создаёт Message(role=assistant), сохраняет partialOutput
  - GenerationService: pending → streaming → cancelled не создаёт Message(role=assistant), сохраняет partialOutput
  - GenerationService: статусы `ColumnConversation` синхронизируются с lifecycle generation
  - GenerationService: запрет двух одновременных active generation в одной колонке
  - SseEventFactory: формат каждого типа события корректен
  - GenerationStreamController: возвращает SSE content-type, корректный поток событий
  - GenerationStreamController: при закрытии клиентского SSE-соединения upstream-запрос останавливается немедленно
- [x] 8.5 Property-тесты
  - Prop. 06: каждый SSE-event `token` дописывается только в соответствующую колонку
  - Prop. 10: ошибка одной generation не меняет статус других колонок
  - Prop. 18: generation отображается через SSE
- [x] 8.6 Чекпоинт: тесты, компиляция, линтер

---

## 9. Generation Cancel (Backend)

- [x] 9.1 `GenerationCancelController`: `POST /api/generations/{generationId}/cancel` — прерывание upstream, перевод в cancelled, сохранение partialOutput (Req.: US-07.2, US-07.5)
- [x] 9.2 Unit-тесты
  - Cancel допустим только для generation в статусе pending или streaming
  - Cancel для completed/error/cancelled возвращает 422
  - После cancel: partialOutput сохраняется, Message(role=assistant) не создаётся
  - Cancel прерывает upstream HTTP-соединение
- [x] 9.3 Property-тесты
  - Prop. 22: cancel прекращает стриминг и переводит generation в cancelled
  - Prop. 25: cancelled generation — partialOutput сохранён, в контекст не включён
- [x] 9.4 Чекпоинт: тесты, компиляция, линтер

---

## 10. Generation Retry & Error Handling (Backend)

- [x] 10.1 `GenerationRetryController`: `POST /api/generations/{generationId}/retry` — повторный запуск на основе последнего user message и подтверждённой истории (Req.: US-03.3)
- [x] 10.2 Unit-тесты
  - Retry создаёт новую generation с тем же user message
  - Retry использует только подтверждённую историю, partialOutput не попадает в контекст
  - Retry допустим только для generation в статусе error или cancelled
  - Ошибка одной колонки не влияет на другие колонки
- [x] 10.3 Property-тесты
  - Prop. 09: ошибка → отображение текста ошибки + кнопка «Повторить запрос»
  - Prop. 10: изоляция ошибок между колонками
- [x] 10.4 Чекпоинт: тесты, компиляция, линтер

---

## 11. Frontend: Landing Screen & Central Prompt

- [x] 11.1 `DesktopRequirementScreen`: заглушка для viewport < 1200px (Req.: US-05.2)
- [x] 11.2 `CentralPromptScreen`: центральное поле ввода, отправка по Enter/кнопке, запуск transition (Req.: US-01.1, US-01.2)
- [x] 11.3 `Vue Transition` + CSS animations: анимация transition центрального экрана → 4-колоночный режим (Req.: US-01.2, US-05.1)
- [x] 11.4 Unit-тесты (Vitest / Vue Test Utils)
  - На старте отображается только центральный prompt
  - При viewport < 1200px — экран-заглушка
  - После submit промпта — transition к workspace
- [x] 11.5 Property-тесты
  - Prop. 01: первый рендер — одно центральное поле, нет 4-колоночной области
  - Prop. 02: после отправки — скрытие поля, появление 4 колонок
  - Prop. 14a: landing — light theme, минимализм, только центральный промпт
- [x] 11.6 Чекпоинт: тесты, компиляция, линтер
- [x] 11.7 Очистка prompt после submit (UX улучшение)

---

## 12. Frontend: Workspace Grid & Column Panel

- [x] 12.1 `WorkspaceGrid`: CSS Grid / Flex 4 колонки равной ширины (Req.: US-05.2)
- [x] 12.2 `ColumnPanel`: заголовок модели (серый label), область сообщений, поле ввода follow-up, состояния loading/error/completed/cancelled (Req.: US-02.1, US-03.1, US-04.1)
- [x] 12.3 `MessageBubble`: рендеринг user/assistant, `markdown-it`, оптимизация через стабильные props / `computed` / `v-memo` (Req.: US-02.2)
- [x] 12.4 Индикаторы «Стоп» / «Отправить» в поле ввода колонки (Req.: US-07.1, US-07.3, US-07.4)
- [x] 12.5 Кнопка «Повторить запрос» при ошибке (Req.: US-03.3)
- [x] 12.6 Анимированный loader при ожидании первого токена (Req.: US-03.1, US-03.2)
- [x] 12.7 Accessibility: aria-labels для кнопок, autofocus на поле ввода колонки
- [x] 12.8 Unit-тесты (Vitest / Vue Test Utils)
  - Заголовок колонки содержит label формата «Провайдер · Модель»
  - Loader отображается при waiting, скрывается при получении первого токена
  - Кнопка «Повторить запрос» отображается только при error
  - «Стоп» отображается при streaming, «Отправить» при idle/completed/error/cancelled
  - Поле ввода присутствует внизу каждой колонки
  - MessageBubble: корректный Markdown-рендеринг, отсутствие лишних перерисовок соседних колонок
- [x] 12.9 Property-тесты
  - Prop. 05: заголовок содержит серый label «Провайдер · Модель»
  - Prop. 07: при waiting отображается loader
  - Prop. 08: на первом токене loader исчезает, появляется текст
  - Prop. 11: поле ввода присутствует внизу колонки
  - Prop. 14b: workspace — light theme, минимализм, только колонки
  - Prop. 15: при >= 1200px — 4 колонки по ~25% ширины
  - Prop. 21: streaming → «Стоп» в поле ввода
  - Prop. 23: completed/error/cancelled → «Отправить» в поле ввода
  - Prop. 24: нажатие «Отправить» отправляет промпт модели
- [x] 12.10 Чекпоинт: тесты, компиляция, линтер

---

## 13. Frontend: Streaming & State Management

- [x] 13.1 `WorkspaceStore` (Pinia): хранение UI-состояния, обновление токенов, переключение статусов (Req.: US-02.2, US-03.1, US-07.1)
- [x] 13.2 `StreamConnectionService`: открытие SSE, разбор событий (meta, token, completed, error, cancelled, heartbeat), cleanup, cancel → EventSource.close() (Req.: US-06.2, US-07.2)
- [x] 13.3 Интеграция: CentralPromptScreen → POST /api/workspaces → 4× SSE stream → Pinia store (Req.: US-01.3)
- [x] 13.4 Интеграция: ColumnPanel follow-up → POST /api/columns/{id}/messages → SSE stream (Req.: US-04.2)
- [x] 13.5 Интеграция: Cancel → POST /api/generations/{id}/cancel + EventSource.close() (Req.: US-07.2)
- [x] 13.6 Интеграция: Retry → POST /api/generations/{id}/retry → SSE stream (Req.: US-03.3)
- [x] 13.7 Loading state: добавить 'transitioning' view между landing → workspace при вызове POST /api/workspaces
- [x] 13.8 Unit-тесты (Vitest)
  - WorkspaceStore: appendToken добавляет токен только в нужную колонку
  - WorkspaceStore: cancelGeneration переводит streaming → idle, сохраняет частичный текст
  - WorkspaceStore: failGeneration устанавливает error state с retryable
  - WorkspaceStore: completeGeneration переводит streaming → idle
  - StreamConnectionService: парсит все типы SSE-событий, включая `meta` и `heartbeat`
  - StreamConnectionService: закрывает EventSource при cancel
  - StreamConnectionService: переподключение не требуется (stateless per-generation)
- [x] 13.9 Property-тесты
  - Prop. 06: токены дописываются только в соответствующую колонку
  - Prop. 19: незавершённый запрос одной колонки не блокирует другие
- [x] 13.10 Чекпоинт: тесты, компиляция, линтер

---

## 14. Frontend: Auto-scroll & UI Polish

- [x] 14.1 `AutoScrollController`: прокрутка колонки вниз при стриминге, удержание последнего токена в видимой области (Req.: US-05.3)
- [x] 14.2 UI polish: light theme, свободное пространство, минимализм (Req.: US-05.1)
- [x] 14.3 Unit-тесты
  - AutoScrollController: при streaming последний токен остаётся видимым
  - AutoScrollController: ручной скролл вверх приостанавливает автоскролл
- [x] 14.4 Property-тесты
  - Prop. 16: при streaming последний токен виден пользователю
- [x] 14.5 Чекпоинт: тесты, компиляция, линтер

---

## 15. Безопасность & Session Scoping

- [ ] 15.1 Middleware: доступ к workspace/column/generation ограничен текущей сессией (Req.: US-06.4)
- [ ] 15.2 Экранирование пользовательского текста на frontend при рендеринге (Req.: US-06.4)
- [ ] 15.3 Unit-тесты
  - Запрос к чужому workspace возвращает 403
  - Запрос к чужой column/generation возвращает 403
  - API key не появляется в логах и ответах
- [ ] 15.4 Чекпоинт: тесты, компиляция, линтер

---

## 16. Integration Tests (Laravel Feature Tests)

- [ ] 16.1 `GET /api/config` — возвращает 4 модели и apiKeyRequired
- [ ] 16.2 `POST /api/session/openrouter-key` и `DELETE /api/session/openrouter-key` — ключ сохраняется и удаляется из сессии; без ключа backend требует ручной ввод
- [ ] 16.3 `POST /api/workspaces` — создаёт workspace, 4 columns, 4 generations; первый prompt в истории каждой колонки
- [ ] 16.4 `GET /api/workspaces/{workspaceId}` — возвращает состояние workspace только для текущей сессии
- [ ] 16.5 `POST /api/columns/{columnId}/messages` — follow-up работает только для одной колонки
- [ ] 16.6 `GET /api/generations/{generationId}/stream` — SSE-поток с корректными событиями (мок OpenRouter); completed generation создаёт assistant message, failed/cancelled не создают
- [ ] 16.7 `POST /api/generations/{generationId}/cancel` — cancel только для pending/streaming; partialOutput сохраняется, Message не создаётся
- [ ] 16.8 `POST /api/generations/{generationId}/retry` — создаёт новую generation на основе последнего user message и только подтверждённой истории
- [ ] 16.9 Изоляция: ошибка upstream одной колонки не затрагивает остальные; разрыв клиентского SSE-соединения останавливает upstream
- [ ] 16.10 Чекпоинт: все integration-тесты зелёные, компиляция, линтер

---

## 17. Component Tests (Vue 3)

- [ ] 17.1 `CentralPromptScreen`: на старте виден только центральный prompt; после submit — 4 колонки
- [ ] 17.2 `WorkspaceGrid`: 4 колонки равной ширины при >= 1200px
- [ ] 17.3 `ColumnPanel`: заголовки «Провайдер · Модель», поле ввода, кнопка «Повторить запрос» при ошибке, индикаторы «Стоп»/«Отправить»
- [ ] 17.4 `ApiKeyModal`: отображается при apiKeyRequired, не хранит в localStorage
- [ ] 17.5 Чекпоинт: все component-тесты зелёные, компиляция, линтер

---

## 18. E2E Tests (Playwright)

- [ ] 18.1 First prompt fan-out: ввод → transition → 4 колонки → 4 потока
- [ ] 18.2 Streaming UX: loader → первый токен → loader исчезает → рост текста
- [ ] 18.3 Column isolation: сломать один upstream → три других продолжают работать
- [ ] 18.4 Follow-up in single column: второй промпт → обновляется только одна колонка
- [ ] 18.5 Retry flow: ошибка → «Повторить запрос» → новая generation → успех
- [ ] 18.6 Cancel flow: стриминг → «Стоп» → cancelled → «Отправить» → частичный текст видим, не в контексте
- [ ] 18.7 Desktop layout: viewport >= 1200px → 4 равные колонки
- [ ] 18.8 Нефункциональные тесты
  - Latency smoke: UI интерактивен при 4 одновременных потоках
  - Session scoping: чужой workspace недоступен
  - Long response: автоскролл и рендер корректны на длинных ответах
- [ ] 18.9 Финальный чекпоинт: все E2E-тесты зелёные, полная компиляция, линтер, ревью

---

## Phase 2: Production Readiness

### Security & Authorization

- [ ] 1. User authentication & workspace ownership

  - Middleware для проверки ownership workspace/column/generation по session_id
  - Возврат 403 при попытке доступа к чужим ресурсам
  - Тесты: запрос к чужому workspace/generation возвращает 403

- [ ] 2. API key security

  - Валидация формата API key перед сохранением
  - Маскирование ключа в логах (только первые/последние 4 символа)
  - Rate limiting для endpoint `/api/session/openrouter-key`

- [ ] 3. Input validation & sanitization

  - Валидация длины промптов (max 10000 символов)
  - Санитизация пользовательского ввода на backend
  - XSS-защита при рендеринге Markdown на frontend

- [ ] 4. Generation cancel ownership check

  - Проверка, что generation принадлежит текущей сессии перед cancel
  - Возврат 403 при попытке отменить чужую generation
  - Тест: cancel чужой generation возвращает 403

- [ ] 5. Generation retry ownership check
  - Проверка, что generation принадлежит текущей сессии перед retry
  - Возврат 403 при попытке retry чужой generation
  - Тест: retry чужой generation возвращает 403

### Performance & Scalability

- [ ] 6. Database optimization

  - Индексы на часто запрашиваемые поля (workspace.session_id, generation.status, column.workspace_id)
  - Eager loading для связей (workspace->columns->messages)
  - Query optimization для getConfirmedHistory()

- [ ] 7. Caching

  - Cache для ModelDefinitions (Redis/file cache)
  - Cache для config endpoint
  - Invalidation strategy для session API keys

- [ ] 8. Rate limiting

  - Rate limit для POST /api/workspaces (5 req/min per session)
  - Rate limit для POST /api/columns/{id}/messages (20 req/min per column)
  - Rate limit для streaming endpoints (10 concurrent per session)

- [ ] 9. Frontend performance optimization
  - Debounce resize listener в useDesktop composable
  - CSS custom properties для transition timing
  - Оптимизация re-renders через v-memo в MessageBubble

### Observability & Monitoring

- [ ] 10. Structured logging

  - Добавить request_id в все логи
  - Логирование всех API calls с latency
  - Error tracking с stack traces

- [ ] 11. Metrics & monitoring

  - Prometheus metrics для API endpoints
  - Metrics для OpenRouter upstream (latency, errors, rate limits)
  - Metrics для active SSE connections

- [ ] 12. Health checks
  - `/health` endpoint для liveness probe
  - `/ready` endpoint для readiness probe (DB + Redis)
  - Graceful shutdown для SSE connections

### Reliability & Error Handling

- [ ] 13. Upstream resilience

  - Retry logic для transient OpenRouter errors (429, 503)
  - Circuit breaker для provider unavailable
  - Fallback error messages для пользователей

- [ ] 14. Database resilience

  - Connection pooling configuration
  - Transaction retry для deadlocks
  - Graceful degradation при DB unavailable

- [ ] 15. SSE connection management
  - Timeout для idle SSE connections (5 min)
  - Cleanup для orphaned generations (pending > 10 min)
  - Reconnection strategy на frontend

### Deployment & DevOps

- [ ] 16. Environment configuration

  - Separate configs для dev/staging/production
  - Secret management (Vault/AWS Secrets Manager)
  - Environment validation на startup

- [ ] 17. Docker & orchestration

  - Multi-stage Dockerfile для production
  - Docker Compose для local development
  - Kubernetes manifests (deployment, service, ingress)

- [ ] 18. CI/CD pipeline
  - Automated tests на каждый commit
  - Linting & code quality checks
  - Automated deployment для staging/production

### Documentation & Maintenance

- [ ] 19. API documentation

  - OpenAPI/Swagger spec для всех endpoints
  - Request/response examples
  - Error codes reference

- [ ] 20. Runbook

  - Deployment procedures
  - Rollback procedures
  - Common issues & troubleshooting

- [ ] 21. Code documentation
  - PHPDoc для всех public methods
  - Architecture decision records (ADR)
  - README с setup instructions
