import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import type {
  ColumnState,
  ColumnStatus,
  ColumnMessage,
  WorkspaceResponse,
  FollowUpResponse,
} from '@/types/workspace';
import type { ModelDefinition } from '@/types/config';

export const useWorkspaceStore = defineStore('workspace', () => {
  const columns = ref<ColumnState[]>([]);
  const workspaceId = ref<string | null>(null);

  const activeColumns = computed(() => columns.value);

  function initWorkspace(
    response: WorkspaceResponse,
    models: ModelDefinition[],
    initialPrompt: string
  ): void {
    workspaceId.value = response.workspaceId;
    columns.value = response.columns.map((columnDto) => {
      const model = models.find((m) => m.code === columnDto.modelCode);
      const generation = response.generations.find((g) => g.columnId === columnDto.id);

      return {
        id: columnDto.id,
        modelCode: columnDto.modelCode,
        providerName: model?.providerName ?? columnDto.modelCode,
        displayName: model?.displayName ?? columnDto.modelCode,
        label: model?.label ?? columnDto.modelCode,
        status: 'waiting' as ColumnStatus,
        messages: [
          {
            id: generation?.userMessageId ?? crypto.randomUUID(),
            role: 'user' as const,
            content: initialPrompt,
          },
        ],
        streamingText: '',
        errorMessage: null,
        generationId: generation?.id ?? null,
        retryable: false,
      };
    });
  }

  function setColumnWaiting(columnId: string, generationId: string): void {
    const column = findColumn(columnId);
    if (!column) return;
    column.status = 'waiting';
    column.generationId = generationId;
    column.errorMessage = null;
    column.retryable = false;
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
    column.retryable = false;
  }

  function failGeneration(columnId: string, error: string, retryable = true): void {
    const column = findColumn(columnId);
    if (!column) return;
    column.status = 'error';
    column.errorMessage = error;
    // Keep streamingText to show partial output to user
    column.retryable = retryable;
  }

  function cancelGeneration(columnId: string): void {
    const column = findColumn(columnId);
    if (!column) return;
    column.status = 'idle';
    column.retryable = false;
  }

  function retryGeneration(columnId: string, generationId: string): void {
    const column = findColumn(columnId);
    if (!column) return;
    column.status = 'waiting';
    column.generationId = generationId;
    column.errorMessage = null;
    column.streamingText = '';
    column.retryable = false;
  }

  function prepareRetry(columnId: string): void {
    const column = findColumn(columnId);
    if (!column) return;
    column.status = 'waiting';
    column.errorMessage = null;
    column.streamingText = '';
    column.retryable = false;
  }

  function addFollowUpMessage(columnId: string, response: FollowUpResponse, prompt: string): void {
    const column = findColumn(columnId);
    if (!column) return;
    column.messages.push({
      id: response.generation.userMessageId,
      role: 'user',
      content: prompt,
    });
    column.status = 'waiting';
    column.generationId = response.generation.id;
    column.errorMessage = null;
    column.streamingText = '';
    column.retryable = false;
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

  function findColumnByGenerationId(generationId: string): ColumnState | undefined {
    const column = columns.value.find((c) => c.generationId === generationId);
    if (!column) {
      console.warn(`[WorkspaceStore] Column not found for generation: ${generationId}`);
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
    prepareRetry,
    addFollowUpMessage,
    addMessage,
    reset,
    findColumnByGenerationId,
  };
});
