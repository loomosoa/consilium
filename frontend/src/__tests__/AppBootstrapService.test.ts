import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { appBootstrapService } from '@/services/bootstrap'
import { useConfigStore } from '@/stores/config'
import type { AppConfig } from '@/types/config'

const mockConfig: AppConfig = {
  models: [
    {
      code: 'nvidia',
      providerName: 'NVIDIA',
      displayName: 'NVIDIA Nemotron 3 Super 120B',
      label: 'NVIDIA',
      openRouterModelId: 'nvidia/nemotron-3-super-120b-a12b:free',
      contextWindow: 8192,
      order: 1,
    },
    {
      code: 'arcee',
      providerName: 'Arcee AI',
      displayName: 'Arcee AI Trinity Large',
      label: 'Arcee',
      openRouterModelId: 'arcee-ai/trinity-large-preview:free',
      contextWindow: 8192,
      order: 2,
    },
    {
      code: 'glm',
      providerName: 'Z.ai',
      displayName: 'Z.ai GLM-4.5 Air',
      label: 'GLM',
      openRouterModelId: 'z-ai/glm-4.5-air:free',
      contextWindow: 32768,
      order: 3,
    },
    {
      code: 'openai-free',
      providerName: 'OpenAI',
      displayName: 'OpenAI GPT OSS 120B',
      label: 'GPT OSS',
      openRouterModelId: 'openai/gpt-oss-120b:free',
      contextWindow: 8192,
      order: 4,
    },
  ],
  apiKeyRequired: true,
  layout: { desktopColumns: 4 },
}

describe('AppBootstrapService', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('initializes config store from backend config', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch')

    // Mock CSRF
    fetchSpy.mockResolvedValueOnce({ ok: true } as Response)

    // Mock config fetch
    fetchSpy.mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve(mockConfig),
    } as Response)

    await appBootstrapService.bootstrap()

    const store = useConfigStore()
    expect(store.loaded).toBe(true)
    expect(store.models).toHaveLength(4)
    expect(store.apiKeyRequired).toBe(true)
    expect(store.desktopColumns).toBe(4)
  })

  it('sets apiKeyRequired to false when env key is present', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch')

    fetchSpy.mockResolvedValueOnce({ ok: true } as Response)

    const configWithEnvKey: AppConfig = { ...mockConfig, apiKeyRequired: false }
    fetchSpy.mockResolvedValueOnce({
      ok: true,
      json: () => Promise.resolve(configWithEnvKey),
    } as Response)

    await appBootstrapService.bootstrap()

    const store = useConfigStore()
    expect(store.apiKeyRequired).toBe(false)
  })

  it('throws when config fetch fails', async () => {
    const fetchSpy = vi.spyOn(globalThis, 'fetch')

    fetchSpy.mockResolvedValueOnce({ ok: true } as Response)
    fetchSpy.mockResolvedValueOnce({ ok: false, status: 500 } as Response)

    await expect(appBootstrapService.bootstrap()).rejects.toThrow('Failed to fetch config: 500')
  })
})
