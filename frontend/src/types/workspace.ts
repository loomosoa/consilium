export type ColumnStatus = 'idle' | 'waiting' | 'streaming' | 'error' | 'cancelled';

export interface ColumnMessage {
  id: string;
  role: 'user' | 'assistant';
  content: string;
}

export interface ColumnState {
  id: string;
  modelCode: string;
  providerName: string;
  displayName: string;
  label: string;
  status: ColumnStatus;
  messages: ColumnMessage[];
  streamingText: string;
  errorMessage: string | null;
  generationId: string | null;
  retryable: boolean;
}

export interface ColumnDto {
  id: string;
  modelCode: string;
  position: number;
  status: string;
}

export interface GenerationDto {
  id: string;
  columnId: string;
  userMessageId: string;
  status: string;
}

export interface WorkspaceResponse {
  workspaceId: string;
  columns: ColumnDto[];
  generations: GenerationDto[];
}

export interface FollowUpResponse {
  columnId: string;
  generation: GenerationDto;
}

export interface RetryResponse {
  generation: GenerationDto;
}

export interface CancelResponse {
  generation: {
    id: string;
    columnId: string;
    status: string;
    partialOutput: string | null;
  };
}

export interface SseMetaEvent {
  generationId: string;
  columnId: string;
  modelCode: string;
  modelLabel: string | null;
  status: string;
}

export interface SseTokenEvent {
  text: string;
  sequence: number;
}

export interface SseCompletedEvent {
  generationId: string;
  assistantMessageId: string;
  finishReason: string;
  promptTokens: number | null;
  completionTokens: number | null;
}

export interface SseErrorEvent {
  generationId: string;
  code: string;
  message: string;
  retryable: boolean;
}

export interface SseCancelledEvent {
  generationId: string;
  partialOutput: string | null;
}
