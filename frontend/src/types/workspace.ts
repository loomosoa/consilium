export type ColumnStatus = 'idle' | 'waiting' | 'streaming' | 'error' | 'cancelled'

export interface ColumnMessage {
  id: string
  role: 'user' | 'assistant'
  content: string
}

export interface ColumnState {
  id: string
  modelCode: string
  providerName: string
  displayName: string
  label: string
  status: ColumnStatus
  messages: ColumnMessage[]
  streamingText: string
  errorMessage: string | null
  generationId: string | null
}
