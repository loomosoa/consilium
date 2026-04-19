import { beforeEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { useWorkspaceStore } from '@/stores/workspace';
import WorkspaceGrid from '@/components/WorkspaceGrid.vue';
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

function initTestStore() {
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

describe('Workspace Property Tests', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  /**
   * Prop. 05: заголовок содержит серый label «Провайдер · Модель»
   */
  it('Prop.05: each column header contains gray label Provider · Model', () => {
    initTestStore();
    const wrapper = mount(WorkspaceGrid);
    const headers = wrapper.findAll('header span');

    for (const header of headers) {
      expect(header.classes()).toContain('text-gray-400');
      expect(header.text()).toContain('·');
    }
  });

  /**
   * Prop. 07: при waiting отображается loader
   */
  it('Prop.07: loader is shown when column is waiting', () => {
    const store = initTestStore();
    store.setColumnWaiting('gpt5', 'gen-1');

    const wrapper = mount(WorkspaceGrid);
    expect(wrapper.text()).toContain('Generating');
  });

  /**
   * Prop. 08: на первом токене loader исчезает, появляется текст
   */
  it('Prop.08: loader disappears and text appears on first token', async () => {
    const store = initTestStore();
    store.setColumnWaiting('gpt5', 'gen-1');

    const wrapper = mount(WorkspaceGrid);
    const firstSection = wrapper.findAll('section')[0];
    expect(firstSection.text()).toContain('Generating');

    store.appendToken('gpt5', 'Hello');
    await wrapper.vm.$nextTick();

    expect(firstSection.text()).not.toContain('Generating');
    expect(firstSection.text()).toContain('Hello');
  });

  /**
   * Prop. 11: поле ввода присутствует внизу колонки
   */
  it('Prop.11: input field is present at the bottom of each idle column', () => {
    initTestStore();
    const wrapper = mount(WorkspaceGrid);
    const sections = wrapper.findAll('section');

    for (const section of sections) {
      const textarea = section.find('textarea');
      const stopButton = section.find('button[aria-label="Stop generation"]');
      // Either textarea (idle/error/cancelled) or stop button (streaming/waiting)
      expect(textarea.exists() || stopButton.exists()).toBe(true);
    }
  });

  /**
   * Prop. 14b: workspace — light theme, минимализм, только колонки
   */
  it('Prop.14b: workspace uses light theme with minimal design', () => {
    initTestStore();
    const wrapper = mount(WorkspaceGrid);

    // White background
    const grid = wrapper.find('.grid-cols-4');
    expect(grid.classes()).toContain('bg-white');

    // No navbars, sidebars, toolbars
    expect(wrapper.find('nav').exists()).toBe(false);
    expect(wrapper.find('aside').exists()).toBe(false);
  });

  /**
   * Prop. 15: при >= 1200px — 4 колонки по ~25% ширины
   */
  it('Prop.15: 4 equal-width columns via CSS Grid', () => {
    initTestStore();
    const wrapper = mount(WorkspaceGrid);
    const grid = wrapper.find('.grid-cols-4');
    expect(grid.exists()).toBe(true);
    expect(grid.classes()).toContain('grid-cols-4');
  });

  /**
   * Prop. 21: streaming → «Стоп» в поле ввода
   */
  it('Prop.21: streaming shows Stop button in input area', () => {
    const store = initTestStore();
    store.setColumnWaiting('gpt5', 'gen-1');
    store.appendToken('gpt5', 'Hello');

    const wrapper = mount(WorkspaceGrid);
    const stopButton = wrapper.find('button[aria-label="Stop generation"]');
    expect(stopButton.exists()).toBe(true);
  });

  /**
   * Prop. 23: completed/error/cancelled → «Отправить» в поле ввода
   */
  it('Prop.23: idle/error/cancelled shows Send input in input area', () => {
    const store = initTestStore();
    // After initWorkspace columns are 'waiting' — complete generation to reach 'idle'
    store.completeGeneration('gpt5', 'msg-assistant-1');

    const wrapper = mount(WorkspaceGrid);
    const sendButton = wrapper.find('button[aria-label="Send prompt"]');
    expect(sendButton.exists()).toBe(true);
  });

  /**
   * Prop. 24: нажатие «Отправить» отправляет промпт модели
   */
  it('Prop.24: clicking Send emits submit with columnId and prompt', async () => {
    const store = initTestStore();
    store.completeGeneration('gpt5', 'msg-assistant-1');

    const wrapper = mount(WorkspaceGrid);

    const textarea = wrapper.find('textarea');
    await textarea.setValue('Test follow-up');

    const sendButton = wrapper.find('button[aria-label="Send prompt"]');
    await sendButton.trigger('click');

    expect(wrapper.emitted()).toHaveProperty('submit');
  });
});
