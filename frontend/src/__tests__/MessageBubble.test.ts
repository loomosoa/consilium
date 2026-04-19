import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import MessageBubble from '@/components/MessageBubble.vue';

describe('MessageBubble', () => {
  it('renders user message with right alignment', () => {
    const wrapper = mount(MessageBubble, {
      props: { message: { id: '1', role: 'user', content: 'Hello' } },
    });
    expect(wrapper.find('.text-right').exists()).toBe(true);
    expect(wrapper.text()).toContain('Hello');
  });

  it('renders assistant message with left alignment', () => {
    const wrapper = mount(MessageBubble, {
      props: { message: { id: '2', role: 'assistant', content: 'Hi there' } },
    });
    expect(wrapper.find('.text-left').exists()).toBe(true);
    expect(wrapper.text()).toContain('Hi there');
  });

  it('user messages have purple background', () => {
    const wrapper = mount(MessageBubble, {
      props: { message: { id: '1', role: 'user', content: 'Hello' } },
    });
    expect(wrapper.find('.bg-purple-50').exists()).toBe(true);
  });

  it('assistant messages have no colored background', () => {
    const wrapper = mount(MessageBubble, {
      props: { message: { id: '2', role: 'assistant', content: 'Hi' } },
    });
    expect(wrapper.find('.bg-purple-50').exists()).toBe(false);
  });

  it('renders assistant message with Markdown', () => {
    const wrapper = mount(MessageBubble, {
      props: { message: { id: '2', role: 'assistant', content: '**Bold** and *italic*' } },
    });
    expect(wrapper.html()).toContain('<strong>Bold</strong>');
    expect(wrapper.html()).toContain('<em>italic</em>');
  });

  it('user messages render as plain text without Markdown', () => {
    const wrapper = mount(MessageBubble, {
      props: { message: { id: '1', role: 'user', content: '**Not bold**' } },
    });
    expect(wrapper.text()).toContain('**Not bold**');
    expect(wrapper.html()).not.toContain('<strong>');
  });
});
