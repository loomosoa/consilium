import { beforeEach, describe, expect, it } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useWorkspaceStore } from '@/stores/workspace';
import type { WorkspaceResponse, ModelDefinition } from '@/types';

const models: ModelDefinition[] = [
  {
    code: 'xai',
    providerName: 'xAI',
    displayName: 'Grok 4.20',
    label: 'xAI · Grok 4.20',
    openRouterModelId: 'x-ai/grok-4.20',
    contextWindow: 131072,
    order: 1,
  },
  {
    code: 'google',
    providerName: 'Google',
    displayName: 'Gemini 3.1 Pro',
    label: 'Google · Gemini 3.1 Pro',
    openRouterModelId: 'google/gemini-3.1-pro',
    contextWindow: 2000000,
    order: 2,
  },
  {
    code: 'zai',
    providerName: 'Z.ai',
    displayName: 'GLM-5.1',
    label: 'Z.ai · GLM-5.1',
    openRouterModelId: 'z-ai/glm-5.1',
    contextWindow: 128000,
    order: 3,
  },
  {
    code: 'openai',
    providerName: 'OpenAI',
    displayName: 'GPT-5.2',
    label: 'OpenAI · GPT-5.2',
    openRouterModelId: 'openai/gpt-5.2',
    contextWindow: 256000,
    order: 4,
  },
];

function makeWorkspaceResponse(): WorkspaceResponse {
  return {
    workspaceId: 'ws-1',
    columns: models.map((m, i) => ({
      id: `col-${m.code}`,
      modelCode: m.code,
      position: i + 1,
      status: 'waiting',
    })),
    generations: models.map((m) => ({
      id: `gen-${m.code}`,
      columnId: `col-${m.code}`,
      userMessageId: `msg-${m.code}`,
      status: 'pending',
    })),
  };
}

describe('WorkspaceStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  describe('initWorkspace', () => {
    it('creates 4 columns from API response', () => {
      const store = useWorkspaceStore();
      const response = makeWorkspaceResponse();

      store.initWorkspace(response, models, 'Hello');

      expect(store.columns).toHaveLength(4);
      expect(store.workspaceId).toBe('ws-1');
    });

    it('maps model metadata to each column', () => {
      const store = useWorkspaceStore();
      const response = makeWorkspaceResponse();

      store.initWorkspace(response, models, 'Hello');

      const xai = store.columns[0];
      expect(xai.providerName).toBe('xAI');
      expect(xai.displayName).toBe('Grok 4.20');
      expect(xai.label).toBe('xAI · Grok 4.20');
    });

    it('sets initial user message from prompt', () => {
      const store = useWorkspaceStore();
      const response = makeWorkspaceResponse();

      store.initWorkspace(response, models, 'Test prompt');

      for (const column of store.columns) {
        expect(column.messages).toHaveLength(1);
        expect(column.messages[0].role).toBe('user');
        expect(column.messages[0].content).toBe('Test prompt');
      }
    });

    it('sets generationId from response', () => {
      const store = useWorkspaceStore();
      const response = makeWorkspaceResponse();

      store.initWorkspace(response, models, 'Hello');

      expect(store.columns[0].generationId).toBe('gen-xai');
    });

    it('sets all columns to waiting status', () => {
      const store = useWorkspaceStore();
      const response = makeWorkspaceResponse();

      store.initWorkspace(response, models, 'Hello');

      for (const column of store.columns) {
        expect(column.status).toBe('waiting');
      }
    });
  });

  describe('appendToken', () => {
    it('adds token only to the target column', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');

      store.appendToken('col-xai', 'Hello ');
      store.appendToken('col-xai', 'world');

      expect(store.columns[0].streamingText).toBe('Hello world');
      expect(store.columns[1].streamingText).toBe('');
      expect(store.columns[2].streamingText).toBe('');
      expect(store.columns[3].streamingText).toBe('');
    });

    it('transitions waiting → streaming on first token', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');

      expect(store.columns[0].status).toBe('waiting');
      store.appendToken('col-xai', 'Hi');
      expect(store.columns[0].status).toBe('streaming');
    });
  });

  describe('completeGeneration', () => {
    it('moves streaming text to messages and resets to idle', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');
      store.appendToken('col-xai', 'Response text');

      store.completeGeneration('col-xai', 'assistant-msg-1');

      expect(store.columns[0].status).toBe('idle');
      expect(store.columns[0].streamingText).toBe('');
      expect(store.columns[0].generationId).toBeNull();
      expect(store.columns[0].messages).toHaveLength(2);
      expect(store.columns[0].messages[1]).toEqual({
        id: 'assistant-msg-1',
        role: 'assistant',
        content: 'Response text',
      });
    });
  });

  describe('failGeneration', () => {
    it('sets error state with message and retryable', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');

      store.failGeneration('col-xai', 'Rate limit exceeded', true);

      expect(store.columns[0].status).toBe('error');
      expect(store.columns[0].errorMessage).toBe('Rate limit exceeded');
      expect(store.columns[0].retryable).toBe(true);
      // streamingText is preserved for partial output display
    });

    it('sets retryable to false for non-retryable errors', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');

      store.failGeneration('col-xai', 'Fatal error', false);

      expect(store.columns[0].retryable).toBe(false);
    });
  });

  describe('cancelGeneration', () => {
    it('transitions to idle and keeps streamingText as partial output', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');
      store.appendToken('col-xai', 'Partial ');

      store.cancelGeneration('col-xai');

      expect(store.columns[0].status).toBe('idle');
      expect(store.columns[0].streamingText).toBe('Partial ');
      expect(store.columns[0].retryable).toBe(false);
    });
  });

  describe('retryGeneration', () => {
    it('resets column to waiting with new generationId', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');
      store.failGeneration('col-xai', 'Error', true);

      store.retryGeneration('col-xai', 'gen-xai-retry');

      expect(store.columns[0].status).toBe('waiting');
      expect(store.columns[0].generationId).toBe('gen-xai-retry');
      expect(store.columns[0].errorMessage).toBeNull();
      expect(store.columns[0].streamingText).toBe('');
    });
  });

  describe('addFollowUpMessage', () => {
    it('adds user message and sets waiting with new generationId', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');
      store.appendToken('col-xai', 'Response');
      store.completeGeneration('col-xai', 'assistant-msg-1');

      store.addFollowUpMessage(
        'col-xai',
        {
          columnId: 'col-xai',
          generation: {
            id: 'gen-xai-2',
            columnId: 'col-xai',
            userMessageId: 'msg-xai-2',
            status: 'pending',
          },
        },
        'Follow up'
      );

      expect(store.columns[0].messages).toHaveLength(3);
      expect(store.columns[0].messages[2].role).toBe('user');
      expect(store.columns[0].messages[2].content).toBe('Follow up');
      expect(store.columns[0].status).toBe('waiting');
      expect(store.columns[0].generationId).toBe('gen-xai-2');
    });
  });

  describe('findColumnByGenerationId', () => {
    it('finds column by its current generationId', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');

      const column = store.findColumnByGenerationId('gen-xai');
      expect(column?.id).toBe('col-xai');
    });

    it('returns undefined for unknown generationId', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');

      const column = store.findColumnByGenerationId('unknown');
      expect(column).toBeUndefined();
    });
  });

  describe('edge cases', () => {
    it('preserves streamingText on error for partial output', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');
      store.appendToken('col-xai', 'Partial response text');

      store.failGeneration('col-xai', 'Network error', true);

      expect(store.columns[0].streamingText).toBe('Partial response text');
      expect(store.columns[0].status).toBe('error');
    });

    it('handles retry when column has no generationId', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');
      store.completeGeneration('col-xai', 'msg-1');

      expect(store.columns[0].generationId).toBeNull();

      store.retryGeneration('col-xai', 'gen-xai-new');

      expect(store.columns[0].generationId).toBe('gen-xai-new');
      expect(store.columns[0].status).toBe('waiting');
    });

    it('clears streamingText on retry', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');
      store.appendToken('col-xai', 'Old partial text');
      store.failGeneration('col-xai', 'Error', true);

      store.retryGeneration('col-xai', 'gen-xai-retry');

      expect(store.columns[0].streamingText).toBe('');
      expect(store.columns[0].status).toBe('waiting');
    });

    it('handles cancel on column without generationId', () => {
      const store = useWorkspaceStore();
      store.initWorkspace(makeWorkspaceResponse(), models, 'Hello');
      store.completeGeneration('col-xai', 'msg-1');

      expect(store.columns[0].generationId).toBeNull();

      store.cancelGeneration('col-xai');

      expect(store.columns[0].status).toBe('idle');
    });
  });
});
