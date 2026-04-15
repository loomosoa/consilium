import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { AppConfig, ModelDefinition } from '@/types/config'

export const useConfigStore = defineStore('config', () => {
  const models = ref<ModelDefinition[]>([])
  const apiKeyRequired = ref(false)
  const desktopColumns = ref(4)
  const loaded = ref(false)

  function initFromConfig(config: AppConfig): void {
    models.value = config.models
    apiKeyRequired.value = config.apiKeyRequired
    desktopColumns.value = config.layout.desktopColumns
    loaded.value = true
  }

  return {
    models,
    apiKeyRequired,
    desktopColumns,
    loaded,
    initFromConfig,
  }
})
