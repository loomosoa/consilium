import type { AppConfig } from '@/types/config'

const API_BASE = '/api'

async function csrf(): Promise<void> {
  await fetch('/sanctum/csrf-cookie', {
    credentials: 'same-origin',
  })
}

async function fetchConfig(): Promise<AppConfig> {
  const response = await fetch(`${API_BASE}/config`, {
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
    },
  })

  if (!response.ok) {
    throw new Error(`Failed to fetch config: ${response.status}`)
  }

  return response.json()
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
  })

  if (!response.ok) {
    const data = await response.json()
    throw new Error(data.errors?.apiKey?.[0] ?? 'Failed to store API key')
  }

  return response.json()
}

async function deleteApiKey(): Promise<void> {
  await fetch(`${API_BASE}/session/openrouter-key`, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
    },
  })
}

export const api = {
  csrf,
  fetchConfig,
  storeApiKey,
  deleteApiKey,
}
