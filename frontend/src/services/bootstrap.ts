import { api } from './api'
import { useConfigStore } from '@/stores/config'

class AppBootstrapService {
  async bootstrap(): Promise<void> {
    // 1. Initialize CSRF for Sanctum
    await api.csrf()

    // 2. Load config from backend
    const config = await api.fetchConfig()

    // 3. Initialize store from config
    const configStore = useConfigStore()
    configStore.initFromConfig(config)
  }
}

export const appBootstrapService = new AppBootstrapService()
