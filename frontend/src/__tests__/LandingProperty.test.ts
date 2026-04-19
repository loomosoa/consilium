import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { ref } from 'vue';
import App from '@/App.vue';

// Mock useDesktop — always desktop for these tests
vi.mock('@/composables/useDesktop', () => ({
  useDesktop: () => ({
    isDesktop: ref(true),
  }),
}));

// Mock bootstrap — skip API calls
vi.mock('@/services/bootstrap', () => ({
  appBootstrapService: {
    bootstrap: vi.fn(async () => {}),
  },
}));

// Mock API service
vi.mock('@/services/api', () => ({
  api: {
    createWorkspace: vi.fn(async () => ({
      workspaceId: 'ws-test',
      columns: [
        { id: 'col-1', modelCode: 'xai', position: 1, status: 'waiting' },
        { id: 'col-2', modelCode: 'google', position: 2, status: 'waiting' },
        { id: 'col-3', modelCode: 'zai', position: 3, status: 'waiting' },
        { id: 'col-4', modelCode: 'openai', position: 4, status: 'waiting' },
      ],
      generations: [
        { id: 'gen-1', columnId: 'col-1', userMessageId: 'msg-1', status: 'pending' },
        { id: 'gen-2', columnId: 'col-2', userMessageId: 'msg-2', status: 'pending' },
        { id: 'gen-3', columnId: 'col-3', userMessageId: 'msg-3', status: 'pending' },
        { id: 'gen-4', columnId: 'col-4', userMessageId: 'msg-4', status: 'pending' },
      ],
    })),
  },
}));

// Mock stream service
vi.mock('@/services/stream', () => ({
  streamConnectionService: {
    openStream: vi.fn(),
    closeStream: vi.fn(),
    closeAll: vi.fn(),
  },
}));

// Mock config store to provide models
vi.mock('@/stores/config', () => ({
  useConfigStore: () => ({
    models: [
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
    ],
    apiKeyRequired: false,
  }),
}));

async function flushPromises(): Promise<void> {
  await new Promise((resolve) => setTimeout(resolve, 0));
}

describe('Landing Property Tests', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  /**
   * Prop. 01: первый рендер — одно центральное поле, нет 4-колоночной области
   */
  it('Prop.01: on first render only central prompt is visible, no 4-column area', async () => {
    const wrapper = mount(App, { global: { plugins: [createPinia()] } });
    await flushPromises();

    // Should show CentralPromptScreen (landing view)
    const textarea = wrapper.find('textarea');
    expect(textarea.exists()).toBe(true);

    // Should NOT show workspace placeholder
    expect(wrapper.text()).not.toContain('4 columns');
  });

  /**
   * Prop. 02: после отправки — скрытие поля, появление 4 колонок
   */
  it('Prop.02: after submit central field hides, workspace area appears', async () => {
    const wrapper = mount(App, { global: { plugins: [createPinia()] } });
    await flushPromises();

    // Type prompt and submit
    const textarea = wrapper.find('textarea');
    await textarea.setValue('Test prompt');
    await textarea.trigger('keydown', { key: 'Enter' });
    await flushPromises();

    // Central prompt should be gone
    expect(wrapper.find('textarea').exists()).toBe(false);

    // Workspace area should appear (WorkspaceGrid renders columns)
    expect(wrapper.find('.grid-cols-4').exists()).toBe(true);
  });

  /**
   * Prop. 14a: landing — light theme, минимализм, только центральный промпт
   */
  it('Prop.14a: landing uses light theme with minimal design', async () => {
    const wrapper = mount(App, { global: { plugins: [createPinia()] } });
    await flushPromises();

    // Main area should have white background
    const main = wrapper.find('main');
    expect(main.classes()).toContain('bg-white');

    // Only one textarea (central prompt)
    const textareas = wrapper.findAll('textarea');
    expect(textareas.length).toBe(1);
  });
});
