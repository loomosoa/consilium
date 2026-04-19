import type { AppConfig } from '@/types/config';
import type {
  WorkspaceResponse,
  FollowUpResponse,
  CancelResponse,
  RetryResponse,
} from '@/types/workspace';

const API_BASE = '/api';

async function csrf(): Promise<void> {
  await fetch('/sanctum/csrf-cookie', {
    credentials: 'same-origin',
  });
}

async function fetchConfig(): Promise<AppConfig> {
  const response = await fetch(`${API_BASE}/config`, {
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error(`Failed to fetch config: ${response.status}`);
  }

  return response.json();
}

async function storeApiKey(apiKey: string): Promise<{ stored: boolean; maskedKey: string }> {
  const response = await fetch(`${API_BASE}/session/openrouter-key`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({ apiKey }),
  });

  if (!response.ok) {
    const data = await response.json();
    throw new Error(data.errors?.apiKey?.[0] ?? 'Failed to store API key');
  }

  return response.json();
}

async function deleteApiKey(): Promise<void> {
  await fetch(`${API_BASE}/session/openrouter-key`, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
    },
  });
}

async function createWorkspace(initialPrompt: string): Promise<WorkspaceResponse> {
  const response = await fetch(`${API_BASE}/workspaces`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({ initialPrompt }),
  });

  if (!response.ok) {
    const data = await response.json().catch(() => null);
    const message =
      data?.errors?.initialPrompt?.[0] ??
      data?.message ??
      `Failed to create workspace: ${response.status}`;
    throw new Error(message);
  }

  return response.json();
}

async function sendFollowUp(columnId: string, prompt: string): Promise<FollowUpResponse> {
  const response = await fetch(`${API_BASE}/columns/${columnId}/messages`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({ prompt }),
  });

  if (!response.ok) {
    const data = await response.json().catch(() => null);
    const message =
      data?.errors?.prompt?.[0] ?? data?.message ?? `Failed to send message: ${response.status}`;
    throw new Error(message);
  }

  return response.json();
}

async function cancelGeneration(generationId: string): Promise<CancelResponse> {
  const response = await fetch(`${API_BASE}/generations/${generationId}/cancel`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
  });

  if (!response.ok) {
    const data = await response.json().catch(() => null);
    throw new Error(data?.message ?? `Failed to cancel generation: ${response.status}`);
  }

  return response.json();
}

async function retryGeneration(generationId: string): Promise<RetryResponse> {
  const response = await fetch(`${API_BASE}/generations/${generationId}/retry`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
  });

  if (!response.ok) {
    const data = await response.json().catch(() => null);
    throw new Error(data?.message ?? `Failed to retry generation: ${response.status}`);
  }

  return response.json();
}

export const api = {
  csrf,
  fetchConfig,
  storeApiKey,
  deleteApiKey,
  createWorkspace,
  sendFollowUp,
  cancelGeneration,
  retryGeneration,
};
