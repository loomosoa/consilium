import type {
  SseMetaEvent,
  SseTokenEvent,
  SseCompletedEvent,
  SseErrorEvent,
  SseCancelledEvent,
} from '@/types/workspace';

export interface StreamCallbacks {
  onMeta: (_data: SseMetaEvent) => void;
  onToken: (_data: SseTokenEvent) => void;
  onCompleted: (_data: SseCompletedEvent) => void;
  onError: (_data: SseErrorEvent) => void;
  onCancelled: (_data: SseCancelledEvent) => void;
}

export class StreamConnectionService {
  private connections = new Map<string, EventSource>();
  private timeouts = new Map<string, ReturnType<typeof setTimeout>>();
  private readonly TIMEOUT_MS = 300_000; // 5 minutes (matches backend timeout)

  openStream(generationId: string, callbacks: StreamCallbacks): void {
    this.closeStream(generationId);

    const url = `/api/generations/${generationId}/stream`;
    const eventSource = new EventSource(url, { withCredentials: true });

    const resetTimeout = () => {
      this.clearTimeout(generationId);
      const timeout = setTimeout(() => {
        callbacks.onError({
          generationId,
          code: 'timeout',
          message: 'Connection timeout',
          retryable: true,
        });
        this.closeStream(generationId);
      }, this.TIMEOUT_MS);
      this.timeouts.set(generationId, timeout);
    };

    resetTimeout();

    eventSource.addEventListener('meta', (e: MessageEvent) => {
      const data = this.parseSseData<SseMetaEvent>(e);
      if (data) {
        resetTimeout();
        callbacks.onMeta(data);
      }
    });

    eventSource.addEventListener('token', (e: MessageEvent) => {
      const data = this.parseSseData<SseTokenEvent>(e);
      if (data) callbacks.onToken(data);
    });

    eventSource.addEventListener('completed', (e: MessageEvent) => {
      const data = this.parseSseData<SseCompletedEvent>(e);
      if (data) {
        callbacks.onCompleted(data);
        this.closeStream(generationId);
      }
    });

    eventSource.addEventListener('error', (e: MessageEvent) => {
      const data = this.parseSseData<SseErrorEvent>(e);
      if (data) {
        callbacks.onError(data);
      }
      this.closeStream(generationId);
    });

    eventSource.addEventListener('cancelled', (e: MessageEvent) => {
      const data = this.parseSseData<SseCancelledEvent>(e);
      if (data) {
        callbacks.onCancelled(data);
      }
      this.closeStream(generationId);
    });

    eventSource.addEventListener('heartbeat', () => {
      resetTimeout();
    });

    eventSource.onerror = () => {
      if (eventSource.readyState === EventSource.CLOSED) {
        callbacks.onError({
          generationId,
          code: 'connection_lost',
          message: 'Connection lost. Please retry.',
          retryable: true,
        });
      }
      this.closeStream(generationId);
    };

    this.connections.set(generationId, eventSource);
  }

  closeStream(generationId: string): void {
    const eventSource = this.connections.get(generationId);
    if (eventSource) {
      if (eventSource.readyState !== EventSource.CLOSED) {
        eventSource.close();
      }
      this.connections.delete(generationId);
    }
    this.clearTimeout(generationId);
  }

  private clearTimeout(generationId: string): void {
    const timeout = this.timeouts.get(generationId);
    if (timeout) {
      clearTimeout(timeout);
      this.timeouts.delete(generationId);
    }
  }

  closeAll(): void {
    for (const generationId of this.connections.keys()) {
      this.closeStream(generationId);
    }
  }

  isConnected(generationId: string): boolean {
    const eventSource = this.connections.get(generationId);
    return eventSource != null && eventSource.readyState !== EventSource.CLOSED;
  }

  private parseSseData<T>(e: MessageEvent): T | null {
    try {
      return JSON.parse(e.data) as T;
    } catch {
      console.warn('[StreamConnectionService] Failed to parse SSE data:', e.data);
      return null;
    }
  }
}

export const streamConnectionService = new StreamConnectionService();
