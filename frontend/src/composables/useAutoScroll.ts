import { type ComponentPublicInstance, onUnmounted, ref, watch, type Ref } from 'vue';
import type { ColumnStatus } from '@/types/workspace';

export interface AutoScrollController {
  /** Register the scrollable container element (use as :ref) */
  register: (el: Element | ComponentPublicInstance | null) => void;
  /** Whether auto-scroll is currently active (not paused by user) */
  isActive: Ref<boolean>;
  /** Manually scroll to bottom and re-activate auto-scroll */
  scrollToBottom: () => void;
}

/**
 * AutoScrollController — scrolls the column's message area down
 * during streaming so the last token stays visible.
 *
 * Pauses when user scrolls up; resumes when user scrolls back to bottom.
 */
export function useAutoScroll(
  status: Ref<ColumnStatus>,
  streamingText?: Ref<string>
): AutoScrollController {
  const container = ref<HTMLElement | null>(null);
  const isActive = ref(true);
  let rafId: number | null = null;

  const THRESHOLD_PX = 40;

  function isNearBottom(el: HTMLElement): boolean {
    return el.scrollHeight - el.scrollTop - el.clientHeight < THRESHOLD_PX;
  }

  function scrollToBottom(): void {
    const el = container.value;
    if (!el) return;
    el.scrollTop = el.scrollHeight;
    isActive.value = true;
  }

  function onUserScroll(): void {
    const el = container.value;
    if (!el) return;

    if (isNearBottom(el)) {
      isActive.value = true;
    } else {
      // User scrolled up — pause auto-scroll
      isActive.value = false;
    }
  }

  function register(el: Element | ComponentPublicInstance | null): void {
    // Cleanup previous
    if (container.value) {
      container.value.removeEventListener('scroll', onUserScroll);
    }

    const htmlEl = el instanceof Element ? (el as HTMLElement) : null;
    container.value = htmlEl;

    if (htmlEl) {
      htmlEl.addEventListener('scroll', onUserScroll, { passive: true });
    }
  }

  // Scroll to bottom when streaming and auto-scroll is active
  // Triggered by: status change, streamingText change, isActive change, container mount
  const stopWatch = watch(
    [status, isActive, () => container.value, ...(streamingText ? [streamingText] : [])],
    () => {
      if (status.value === 'streaming' && isActive.value) {
        if (rafId !== null) {
          cancelAnimationFrame(rafId);
        }
        rafId = requestAnimationFrame(() => {
          scrollToBottom();
          rafId = null;
        });
      }
    }
  );

  // Also scroll on waiting status (initial load)
  watch(status, (newStatus) => {
    if (newStatus === 'waiting' && isActive.value) {
      if (rafId !== null) {
        cancelAnimationFrame(rafId);
      }
      rafId = requestAnimationFrame(() => {
        scrollToBottom();
        rafId = null;
      });
    }
  });

  onUnmounted(() => {
    stopWatch();
    if (rafId !== null) {
      cancelAnimationFrame(rafId);
    }
    if (container.value) {
      container.value.removeEventListener('scroll', onUserScroll);
    }
  });

  return { register, isActive, scrollToBottom };
}
