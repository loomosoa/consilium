import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import type { ColumnState, ColumnStatus, ColumnMessage } from '@/types/workspace';
import type { ModelDefinition } from '@/types/config';

export const useWorkspaceStore = defineStore('workspace', () => {
  const columns = ref<ColumnState[]>([]);
  const workspaceId = ref<string | null>(null);

  const activeColumns = computed(() => columns.value);

  function initWorkspace(id: string, models: ModelDefinition[]): void {
    workspaceId.value = id;
    columns.value = models.map((model) => ({
      id: model.code,
      modelCode: model.code,
      providerName: model.providerName,
      displayName: model.displayName,
      label: model.label,
      status: 'idle' as ColumnStatus,
      messages: [],
      streamingText: '',
      errorMessage: null,
      generationId: null,
    }));
  }

  function setColumnWaiting(columnId: string, generationId: string): void {
    const column = findColumn(columnId);
    if (!column) return;
    column.status = 'waiting';
    column.generationId = generationId;
    column.errorMessage = null;
  }

  function appendToken(columnId: string, token: string): void {
    const column = findColumn(columnId);
    if (!column) return;
    if (column.status === 'waiting') {
      column.status = 'streaming';
    }
    column.streamingText += token;
  }

  function completeGeneration(columnId: string, messageId: string): void {
    const column = findColumn(columnId);
    if (!column) return;
    if (column.streamingText) {
      column.messages.push({
        id: messageId,
        role: 'assistant',
        content: column.streamingText,
      });
    }
    column.streamingText = '';
    column.status = 'idle';
    column.generationId = null;
  }

  function failGeneration(columnId: string, error: string): void {
    const column = findColumn(columnId);
    if (!column) return;
    column.status = 'error';
    column.errorMessage = error;
    column.streamingText = '';
  }

  function cancelGeneration(columnId: string): void {
    const column = findColumn(columnId);
    if (!column) return;
    column.status = 'idle';
    // Keep streamingText as partial output for display
    // Note: streamingText will be cleared on next generation
  }

  function retryGeneration(columnId: string, generationId: string): void {
    const column = findColumn(columnId);
    if (!column) return;
    column.status = 'waiting';
    column.generationId = generationId;
    column.errorMessage = null;
    column.streamingText = '';
  }

  function addMessage(columnId: string, message: ColumnMessage): void {
    const column = findColumn(columnId);
    if (!column) return;
    column.messages.push(message);
  }

  function reset(): void {
    columns.value = [];
    workspaceId.value = null;
  }

  function findColumn(columnId: string): ColumnState | undefined {
    const column = columns.value.find((c) => c.id === columnId);
    if (!column) {
      console.warn(`[WorkspaceStore] Column not found: ${columnId}`);
    }
    return column;
  }

  return {
    columns,
    workspaceId,
    activeColumns,
    initWorkspace,
    setColumnWaiting,
    appendToken,
    completeGeneration,
    failGeneration,
    cancelGeneration,
    retryGeneration,
    addMessage,
    reset,
  };
});
