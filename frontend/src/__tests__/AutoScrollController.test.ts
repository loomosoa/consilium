import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref, nextTick, type Ref } from 'vue';
import { useAutoScroll } from '@/composables/useAutoScroll';
import type { ColumnStatus } from '@/types/workspace';

function createScrollContainer(scrollHeight = 1000, clientHeight = 200): HTMLElement {
  const el = document.createElement('div');

  Object.defineProperty(el, 'scrollHeight', { value: scrollHeight, writable: true });
  Object.defineProperty(el, 'scrollTop', { value: scrollHeight, writable: true });
  Object.defineProperty(el, 'clientHeight', { value: clientHeight, writable: true });

  return el;
}

describe('AutoScrollController', () => {
  let status: Ref<ColumnStatus>;
  let streamingText: Ref<string>;

  beforeEach(() => {
    status = ref<ColumnStatus>('idle');
    streamingText = ref('');
  });

  describe('auto-scroll during streaming', () => {
    it('scrolls to bottom when status becomes streaming', async () => {
      const controller = useAutoScroll(status, streamingText);
      const el = createScrollContainer();

      controller.register(el);
      status.value = 'streaming';

      await nextTick();
      await nextTick(); // wait for requestAnimationFrame

      // scrollTop should be set to scrollHeight
      expect(el.scrollTop).toBe(el.scrollHeight);
    });

    it('scrolls to bottom when streamingText changes during streaming', async () => {
      const controller = useAutoScroll(status, streamingText);
      const el = createScrollContainer();

      controller.register(el);
      status.value = 'streaming';
      await nextTick();

      streamingText.value = 'Hello';
      await nextTick();
      await nextTick();

      expect(el.scrollTop).toBe(el.scrollHeight);
    });

    it('does not scroll when status is idle', async () => {
      const controller = useAutoScroll(status, streamingText);
      const el = createScrollContainer();

      // Set scrollTop to 0
      Object.defineProperty(el, 'scrollTop', { value: 0, writable: true });

      controller.register(el);
      streamingText.value = 'Some text';
      await nextTick();
      await nextTick();

      expect(el.scrollTop).toBe(0);
    });
  });

  describe('manual scroll pause', () => {
    it('pauses auto-scroll when user scrolls up', async () => {
      const controller = useAutoScroll(status, streamingText);
      const el = createScrollContainer();

      controller.register(el);
      status.value = 'streaming';
      await nextTick();

      // Simulate user scrolling up
      Object.defineProperty(el, 'scrollTop', { value: 100, writable: true });
      Object.defineProperty(el, 'scrollHeight', { value: 1000, writable: true });
      Object.defineProperty(el, 'clientHeight', { value: 200, writable: true });

      el.dispatchEvent(new Event('scroll'));

      expect(controller.isActive.value).toBe(false);
    });

    it('resumes auto-scroll when user scrolls back to bottom', async () => {
      const controller = useAutoScroll(status, streamingText);
      const el = createScrollContainer();

      controller.register(el);
      status.value = 'streaming';
      await nextTick();

      // Scroll up first
      Object.defineProperty(el, 'scrollTop', { value: 100, writable: true });
      el.dispatchEvent(new Event('scroll'));
      expect(controller.isActive.value).toBe(false);

      // Scroll back to bottom
      Object.defineProperty(el, 'scrollTop', { value: 970, writable: true });
      el.dispatchEvent(new Event('scroll'));

      expect(controller.isActive.value).toBe(true);
    });

    it('does not auto-scroll when paused by user', async () => {
      const controller = useAutoScroll(status, streamingText);
      const el = createScrollContainer();

      controller.register(el);
      status.value = 'streaming';
      await nextTick();

      // Pause auto-scroll
      controller.isActive.value = false;

      // Reset scrollTop to simulate it staying
      Object.defineProperty(el, 'scrollTop', { value: 100, writable: true });

      streamingText.value = 'New token';
      await nextTick();
      await nextTick();

      // Should NOT have scrolled since isActive is false
      expect(el.scrollTop).toBe(100);
    });
  });

  describe('scrollToBottom', () => {
    it('scrolls to bottom and re-activates auto-scroll', async () => {
      const controller = useAutoScroll(status, streamingText);
      const el = createScrollContainer();

      controller.register(el);
      status.value = 'streaming';
      await nextTick();

      // Pause
      controller.isActive.value = false;
      Object.defineProperty(el, 'scrollTop', { value: 100, writable: true });

      controller.scrollToBottom();

      expect(el.scrollTop).toBe(el.scrollHeight);
      expect(controller.isActive.value).toBe(true);
    });
  });

  describe('cleanup', () => {
    it('removes scroll listener on re-register', () => {
      const controller = useAutoScroll(status, streamingText);
      const el1 = createScrollContainer();
      const el2 = createScrollContainer();

      const removeSpy1 = vi.spyOn(el1, 'removeEventListener');
      controller.register(el1);
      controller.register(el2);

      expect(removeSpy1).toHaveBeenCalledWith('scroll', expect.any(Function));
    });
  });
});
