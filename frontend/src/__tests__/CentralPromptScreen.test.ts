import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CentralPromptScreen from '@/components/CentralPromptScreen.vue';

describe('CentralPromptScreen', () => {
  it('renders with a textarea and submit button', () => {
    const wrapper = mount(CentralPromptScreen);
    expect(wrapper.find('textarea').exists()).toBe(true);
    expect(wrapper.find('button').exists()).toBe(true);
  });

  it('submit button is disabled when prompt is empty', () => {
    const wrapper = mount(CentralPromptScreen);
    const button = wrapper.find('button');
    expect(button.attributes('disabled')).toBeDefined();
  });

  it('emits submit with prompt text on button click', async () => {
    const wrapper = mount(CentralPromptScreen);

    await wrapper.find('textarea').setValue('Hello, world!');
    const button = wrapper.find('button');
    expect(button.attributes('disabled')).toBeUndefined();

    await button.trigger('click');
    expect(wrapper.emitted()).toHaveProperty('submit');
    expect(wrapper.emitted('submit')![0]).toEqual(['Hello, world!']);
  });

  it('emits submit on Enter key', async () => {
    const wrapper = mount(CentralPromptScreen);

    await wrapper.find('textarea').setValue('Test prompt');
    await wrapper.find('textarea').trigger('keydown', { key: 'Enter' });

    expect(wrapper.emitted()).toHaveProperty('submit');
    expect(wrapper.emitted('submit')![0]).toEqual(['Test prompt']);
  });

  it('does not submit on Shift+Enter', async () => {
    const wrapper = mount(CentralPromptScreen);

    await wrapper.find('textarea').setValue('Test prompt');
    await wrapper.find('textarea').trigger('keydown', { key: 'Enter', shiftKey: true });

    expect(wrapper.emitted('submit')).toBeUndefined();
  });

  it('does not submit with whitespace-only prompt', async () => {
    const wrapper = mount(CentralPromptScreen);

    await wrapper.find('textarea').setValue('   ');
    await wrapper.find('textarea').trigger('keydown', { key: 'Enter' });

    expect(wrapper.emitted('submit')).toBeUndefined();
  });

  it('displays app title', () => {
    const wrapper = mount(CentralPromptScreen);
    expect(wrapper.text()).toContain('Consilium');
  });

  it('clears prompt after successful submit', async () => {
    const wrapper = mount(CentralPromptScreen);

    await wrapper.find('textarea').setValue('Hello');
    await wrapper.find('button').trigger('click');

    expect((wrapper.find('textarea').element as HTMLTextAreaElement).value).toBe('');
  });
});
