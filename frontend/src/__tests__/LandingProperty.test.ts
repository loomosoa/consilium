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
