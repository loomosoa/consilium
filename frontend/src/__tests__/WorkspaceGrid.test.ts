import { beforeEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import WorkspaceGrid from '@/components/WorkspaceGrid.vue';
import { useWorkspaceStore } from '@/stores/workspace';
import type { ModelDefinition } from '@/types';

const models: ModelDefinition[] = [
  {
    code: 'gpt5',
    providerName: 'OpenAI',
    displayName: 'GPT-5.2',
    label: 'OpenAI · GPT-5.2',
    openRouterModelId: 'openai/gpt5',
    contextWindow: 128000,
    order: 1,
  },
  {
    code: 'glm5',
    providerName: 'Z.ai',
    displayName: 'GLM5.1',
    label: 'Z.ai · GLM5.1',
    openRouterModelId: 'zai/glm5',
    contextWindow: 64000,
    order: 2,
  },
  {
    code: 'grok4',
    providerName: 'xAI',
    displayName: 'Grok 4.20',
    label: 'xAI · Grok 4.20',
    openRouterModelId: 'xai/grok4',
    contextWindow: 96000,
    order: 3,
  },
  {
    code: 'gemini3',
    providerName: 'Google',
    displayName: 'Gemini 3.1 Pro',
    label: 'Google · Gemini 3.1 Pro',
    openRouterModelId: 'google/gemini3',
    contextWindow: 128000,
    order: 4,
  },
];

function createTestStore() {
  const store = useWorkspaceStore();
  store.initWorkspace(
    {
      workspaceId: 'ws-1',
      columns: models.map((m, i) => ({
        id: m.code,
        modelCode: m.code,
        position: i + 1,
        status: 'waiting',
      })),
      generations: models.map((m) => ({
        id: `gen-${m.code}`,
        columnId: m.code,
        userMessageId: `msg-${m.code}`,
        status: 'pending',
      })),
    },
    models,
    'Test prompt'
  );
  return store;
}

describe('WorkspaceGrid', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('renders 4 columns', () => {
    createTestStore();
    const wrapper = mount(WorkspaceGrid);
    const sections = wrapper.findAll('section');
    expect(sections.length).toBe(4);
  });

  it('uses CSS Grid with 4 equal columns', () => {
    createTestStore();
    const wrapper = mount(WorkspaceGrid);
    const grid = wrapper.find('.grid-cols-4');
    expect(grid.exists()).toBe(true);
  });

  it('each column has header with provider · model label', () => {
    createTestStore();
    const wrapper = mount(WorkspaceGrid);
    const headers = wrapper.findAll('header span');
    expect(headers[0].text()).toBe('OpenAI · GPT-5.2');
    expect(headers[1].text()).toBe('Z.ai · GLM5.1');
    expect(headers[2].text()).toBe('xAI · Grok 4.20');
    expect(headers[3].text()).toBe('Google · Gemini 3.1 Pro');
  });
});
