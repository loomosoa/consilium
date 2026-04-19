import { beforeEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import ColumnPanel from '@/components/ColumnPanel.vue';
import type { ColumnState } from '@/types/workspace';

const idleColumn: ColumnState = {
  id: 'gpt5',
  modelCode: 'gpt5',
  providerName: 'OpenAI',
  displayName: 'GPT-5.2',
  label: 'OpenAI · GPT-5.2',
  status: 'idle',
  messages: [],
  streamingText: '',
  errorMessage: null,
  generationId: null,
};

describe('ColumnPanel', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('shows provider · model label in header', () => {
    const wrapper = mount(ColumnPanel, { props: { column: idleColumn } });
    expect(wrapper.find('header span').text()).toBe('OpenAI · GPT-5.2');
  });

  it('shows loader when status is waiting', () => {
    const column = { ...idleColumn, status: 'waiting' as const };
    const wrapper = mount(ColumnPanel, { props: { column } });
    expect(wrapper.text()).toContain('Generating');
  });

  it('hides loader when status is streaming', () => {
    const column = { ...idleColumn, status: 'streaming' as const, streamingText: 'Hello' };
    const wrapper = mount(ColumnPanel, { props: { column } });
    expect(wrapper.text()).not.toContain('Generating');
  });

  it('shows stop button when streaming', () => {
    const column = { ...idleColumn, status: 'streaming' as const };
    const wrapper = mount(ColumnPanel, { props: { column } });
    const button = wrapper.find('button[aria-label="Stop generation"]');
    expect(button.exists()).toBe(true);
  });

  it('shows stop button when waiting', () => {
    const column = { ...idleColumn, status: 'waiting' as const };
    const wrapper = mount(ColumnPanel, { props: { column } });
    const button = wrapper.find('button[aria-label="Stop generation"]');
    expect(button.exists()).toBe(true);
  });

  it('shows send input when idle', () => {
    const wrapper = mount(ColumnPanel, { props: { column: idleColumn } });
    const textarea = wrapper.find('textarea');
    expect(textarea.exists()).toBe(true);
  });

  it('shows send input when error', () => {
    const column = { ...idleColumn, status: 'error' as const, errorMessage: 'Rate limit' };
    const wrapper = mount(ColumnPanel, { props: { column } });
    const textarea = wrapper.find('textarea');
    expect(textarea.exists()).toBe(true);
  });

  it('shows retry button when error', () => {
    const column = { ...idleColumn, status: 'error' as const, errorMessage: 'Rate limit' };
    const wrapper = mount(ColumnPanel, { props: { column } });
    expect(wrapper.text()).toContain('Повторить запрос');
  });

  it('does not show retry button when idle', () => {
    const wrapper = mount(ColumnPanel, { props: { column: idleColumn } });
    expect(wrapper.text()).not.toContain('Повторить запрос');
  });

  it('emits cancel when stop is clicked', async () => {
    const column = { ...idleColumn, status: 'streaming' as const };
    const wrapper = mount(ColumnPanel, { props: { column } });
    await wrapper.find('button[aria-label="Stop generation"]').trigger('click');
    expect(wrapper.emitted()).toHaveProperty('cancel');
    expect(wrapper.emitted('cancel')![0]).toEqual(['gpt5']);
  });

  it('emits retry when retry button is clicked', async () => {
    const column = { ...idleColumn, status: 'error' as const, errorMessage: 'Rate limit' };
    const wrapper = mount(ColumnPanel, { props: { column } });
    await wrapper.find('button[aria-label="Retry request"]').trigger('click');
    expect(wrapper.emitted()).toHaveProperty('retry');
    expect(wrapper.emitted('retry')![0]).toEqual(['gpt5']);
  });

  it('emits submit when follow-up is sent', async () => {
    const wrapper = mount(ColumnPanel, { props: { column: idleColumn } });
    await wrapper.find('textarea').setValue('Follow-up question');
    await wrapper.find('button[aria-label="Send prompt"]').trigger('click');
    expect(wrapper.emitted()).toHaveProperty('submit');
    expect(wrapper.emitted('submit')![0]).toEqual(['gpt5', 'Follow-up question']);
  });

  it('shows error message in red', () => {
    const column = { ...idleColumn, status: 'error' as const, errorMessage: 'Rate limit exceeded' };
    const wrapper = mount(ColumnPanel, { props: { column } });
    const errorDiv = wrapper.find('.bg-red-50');
    expect(errorDiv.exists()).toBe(true);
    expect(errorDiv.text()).toContain('Rate limit exceeded');
  });

  it('shows streaming text', () => {
    const column = { ...idleColumn, status: 'streaming' as const, streamingText: 'Hello world' };
    const wrapper = mount(ColumnPanel, { props: { column } });
    expect(wrapper.text()).toContain('Hello world');
  });
});
