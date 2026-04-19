# Epic 13 Improvements

Документация исправлений после code review Epic 13.

## Критичные исправления

### #3: Memory leak в StreamConnectionService
**Проблема**: EventSource оставался в Map даже после auto-close  
**Решение**: Проверка `readyState` перед `close()`, гарантированное удаление из Map

```typescript
closeStream(generationId: string): void {
  const eventSource = this.connections.get(generationId);
  if (eventSource) {
    if (eventSource.readyState !== EventSource.CLOSED) {
      eventSource.close();
    }
    this.connections.delete(generationId);
  }
  this.clearTimeout(generationId);
}
```

### #4: Timeout для SSE connections
**Проблема**: Зависший backend без heartbeat приводит к бесконечному ожиданию  
**Решение**: 30-секундный timeout с reset на каждый heartbeat

```typescript
private timeouts = new Map<string, ReturnType<typeof setTimeout>>();
private readonly TIMEOUT_MS = 30000; // 2x heartbeat interval

const resetTimeout = () => {
  this.clearTimeout(generationId);
  const timeout = setTimeout(() => {
    callbacks.onError({
      generationId,
      code: 'timeout',
      message: 'Connection timeout',
      retryable: true,
    });
    this.closeStream(generationId);
  }, this.TIMEOUT_MS);
  this.timeouts.set(generationId, timeout);
};

eventSource.addEventListener('heartbeat', () => {
  resetTimeout();
});
```

## Важные исправления

### #1: Network error handling
**Проблема**: `onerror` только закрывал соединение без уведомления пользователя  
**Решение**: Проверка `readyState` и отправка error callback

```typescript
eventSource.onerror = () => {
  if (eventSource.readyState === EventSource.CLOSED) {
    callbacks.onError({
      generationId,
      code: 'connection_lost',
      message: 'Connection lost. Please retry.',
      retryable: true,
    });
  }
  this.closeStream(generationId);
};
```

### #2: Race condition в cancelStream
**Проблема**: Cancel до установки `generationId` приводил к no-op  
**Решение**: Optimistic cancel в store

```typescript
async function cancelStream(columnId: string): Promise<void> {
  const column = workspaceStore.columns.find((c) => c.id === columnId);
  if (!column) return;

  if (!column.generationId) {
    // Race condition: cancel called before generationId set
    workspaceStore.cancelGeneration(columnId);
    return;
  }
  // ... rest
}
```

### #5: Partial output при ошибке
**Проблема**: При ошибке пользователь терял весь накопленный текст  
**Решение**: Сохранение `streamingText` в store + отображение в UI

**Store**:
```typescript
function failGeneration(columnId: string, error: string, retryable = true): void {
  const column = findColumn(columnId);
  if (!column) return;
  column.status = 'error';
  column.errorMessage = error;
  // Keep streamingText to show partial output to user
  column.retryable = retryable;
}
```

**UI** (`ColumnPanel.vue`):
```vue
<div v-if="(isError || isCancelled) && column.streamingText" class="mt-2 border-l-2 border-gray-300 pl-2">
  <p class="text-xs text-gray-400 mb-1">
    {{ isError ? 'Partial response before error:' : 'Generation stopped:' }}
  </p>
  <div class="text-sm text-gray-600">
    {{ column.streamingText }}
  </div>
</div>
```

## Тестирование

### #6: Edge case tests
Добавлено 9 новых тестов:

**StreamConnectionService**:
- Timeout при отсутствии heartbeat
- Reset timeout на heartbeat
- Connection lost error
- Concurrent cancel calls
- Timeout cleanup при normal close

**WorkspaceStore**:
- Сохранение partial output при ошибке
- Retry без generationId
- Очистка streamingText при retry
- Cancel без generationId

## Результаты

- **Тесты**: 91/91 passed (+9 новых)
- **TypeScript**: 0 errors
- **Linter**: 0 errors (21 pre-existing warnings)

## Файлы изменены

- `src/services/stream.ts` — timeout, memory leak fix, error handling
- `src/composables/useWorkspaceActions.ts` — race condition fix
- `src/stores/workspace.ts` — partial output preservation
- `src/components/ColumnPanel.vue` — partial output UI
- `src/__tests__/StreamConnectionService.test.ts` — +5 edge case tests
- `src/__tests__/WorkspaceStore.test.ts` — +4 edge case tests
