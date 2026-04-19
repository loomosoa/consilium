import { ref } from 'vue';
import { api } from '@/services/api';
import { streamConnectionService } from '@/services/stream';
import { useWorkspaceStore } from '@/stores/workspace';
import { useConfigStore } from '@/stores/config';
import type { StreamCallbacks } from '@/services/stream';

type AppView = 'landing' | 'transitioning' | 'workspace';

export function useWorkspaceActions() {
  const workspaceStore = useWorkspaceStore();
  const configStore = useConfigStore();

  const currentView = ref<AppView>('landing');
  const submitError = ref<string | null>(null);

  function createStreamCallbacks(columnId: string): StreamCallbacks {
    return {
      onMeta() {
        // Meta event received — column already in 'waiting' state
      },
      onToken(data) {
        workspaceStore.appendToken(columnId, data.text);
      },
      onCompleted(data) {
        workspaceStore.completeGeneration(columnId, data.assistantMessageId);
      },
      onError(data) {
        workspaceStore.failGeneration(columnId, data.message, data.retryable);
      },
      onCancelled() {
        workspaceStore.cancelGeneration(columnId);
      },
    };
  }

  async function startWorkspace(initialPrompt: string): Promise<void> {
    submitError.value = null;
    currentView.value = 'transitioning';

    try {
      const response = await api.createWorkspace(initialPrompt);
      workspaceStore.initWorkspace(response, configStore.models, initialPrompt);
      currentView.value = 'workspace';

      for (const generation of response.generations) {
        const column = workspaceStore.columns.find((c) => c.id === generation.columnId);
        if (column) {
          const callbacks = createStreamCallbacks(column.id);
          streamConnectionService.openStream(generation.id, callbacks);
        }
      }
    } catch (e) {
      submitError.value = e instanceof Error ? e.message : 'Failed to create workspace';
      currentView.value = 'landing';
    }
  }

  async function sendFollowUp(columnId: string, prompt: string): Promise<void> {
    try {
      const response = await api.sendFollowUp(columnId, prompt);
      workspaceStore.addFollowUpMessage(columnId, response, prompt);

      const callbacks = createStreamCallbacks(columnId);
      streamConnectionService.openStream(response.generation.id, callbacks);
    } catch (e) {
      const message = e instanceof Error ? e.message : 'Failed to send message';
      workspaceStore.failGeneration(columnId, message, false);
    }
  }

  async function cancelStream(columnId: string): Promise<void> {
    const column = workspaceStore.columns.find((c) => c.id === columnId);
    if (!column) return;

    if (!column.generationId) {
      // Race condition: cancel called before generationId set
      // Optimistically cancel in store
      workspaceStore.cancelGeneration(columnId);
      return;
    }

    const generationId = column.generationId;

    streamConnectionService.closeStream(generationId);

    try {
      await api.cancelGeneration(generationId);
    } catch {
      // Even if the API call fails, the SSE connection is already closed.
    }

    workspaceStore.cancelGeneration(columnId);
  }

  async function retryStream(columnId: string): Promise<void> {
    const column = workspaceStore.columns.find((c) => c.id === columnId);
    if (!column?.generationId) return;

    const oldGenerationId = column.generationId;

    try {
      const response = await api.retryGeneration(oldGenerationId);
      workspaceStore.retryGeneration(columnId, response.generation.id);

      const callbacks = createStreamCallbacks(columnId);
      streamConnectionService.openStream(response.generation.id, callbacks);
    } catch (e) {
      const message = e instanceof Error ? e.message : 'Failed to retry generation';
      workspaceStore.failGeneration(columnId, message, true);
    }
  }

  function cleanup(): void {
    streamConnectionService.closeAll();
    workspaceStore.reset();
    currentView.value = 'landing';
    submitError.value = null;
  }

  return {
    currentView,
    submitError,
    startWorkspace,
    sendFollowUp,
    cancelStream,
    retryStream,
    cleanup,
  };
}
