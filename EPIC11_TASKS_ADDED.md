# Epic 11: Добавленные задачи из ревью

## Задачи добавлены в tasks.md

### Epic 12: Frontend: Workspace Grid & Column Panel

**12.7 Accessibility: aria-labels для кнопок, autofocus на поле ввода колонки**
- Добавить `aria-label` для кнопок Submit/Stop
- Добавить `aria-disabled` для disabled состояний
- Добавить `autofocus` на поле ввода активной колонки
- Улучшить keyboard navigation

**Приоритет:** Medium  
**Обоснование:** Важно для accessibility, но не блокирует функциональность MVP

---

### Epic 13: Frontend: Streaming & State Management

**13.7 Loading state: добавить 'transitioning' view между landing → workspace**
- Расширить `AppView` type: `'landing' | 'transitioning' | 'workspace'`
- Показывать spinner при вызове `POST /api/workspaces`
- Обрабатывать ошибки и возвращать на landing при неудаче
- Добавить тест для transitioning state

**Приоритет:** Medium  
**Обоснование:** Будет актуально при интеграции с API, улучшает UX при медленном соединении

---

### Phase 2: Performance & Scalability

**9. Frontend performance optimization**
- **Debounce resize listener в useDesktop composable**
  - Использовать `@vueuse/core` `useDebounceFn` с 100ms delay
  - Уменьшить количество вызовов при resize
- **CSS custom properties для transition timing**
  - Вынести timing в `:root` переменные
  - Улучшить maintainability и консистентность
- **Оптимизация re-renders через v-memo в MessageBubble**
  - Использовать `v-memo` для стабилизации рендеринга
  - Предотвратить лишние перерисовки соседних колонок

**Приоритет:** Low  
**Обоснование:** Оптимизация производительности, не критично для MVP

---

## Задачи НЕ добавлены (слишком мелкие)

### Очистка prompt после submit
**Описание:** Очищать `prompt.value = ''` после успешной отправки в `CentralPromptScreen`

**Почему не добавлено:**
- Слишком мелкая задача (1 строка кода)
- Не критично для MVP
- Можно добавить в процессе работы над Epic 12/13
- Не требует отдельного тестирования

**Рекомендация:** Добавить при первом редактировании `CentralPromptScreen.vue`

---

## Итоговая статистика

| Epic | Добавлено задач | Приоритет |
|---|---|---|
| Epic 12 | 1 задача (12.7) | Medium |
| Epic 13 | 1 задача (13.7) | Medium |
| Phase 2 | 1 задача (9) | Low |
| **Итого** | **3 задачи** | — |

---

## Обновлённая нумерация

### Epic 12
- 12.1 — 12.6: без изменений
- **12.7**: Accessibility (новая)
- 12.8: Unit-тесты (было 12.7)
- 12.9: Property-тесты (было 12.8)
- 12.10: Чекпоинт (было 12.9)

### Epic 13
- 13.1 — 13.6: без изменений
- **13.7**: Loading state (новая)
- 13.8: Unit-тесты (было 13.7)
- 13.9: Property-тесты (было 13.8)
- 13.10: Чекпоинт (было 13.9)

### Phase 2: Performance & Scalability
- 6 — 8: без изменений
- **9**: Frontend performance optimization (новая)

### Phase 2: Observability & Monitoring
- 10: Structured logging (было 9)
- 11: Metrics & monitoring (было 10)
- 12: Health checks (было 11)

### Phase 2: Reliability & Error Handling
- 13: Upstream resilience (было 12)
- 14: Database resilience (было 13)
- 15: SSE connection management (было 14)

### Phase 2: Deployment & DevOps
- 16: Environment configuration (было 15)
- 17: Docker & orchestration (было 16)
- 18: CI/CD pipeline (было 17)

### Phase 2: Documentation & Maintenance
- 19: API documentation (было 18)
- 20: Runbook (было 19)
- 21: Code documentation (было 20)

---

## Следующие шаги

1. ✅ Задачи добавлены в `tasks.md`
2. ✅ Нумерация исправлена во всех эпиках
3. ✅ Приоритеты указаны
4. 🚀 Готов к Epic 12: Workspace Grid & Column Panel
