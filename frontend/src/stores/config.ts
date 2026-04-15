import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import type { AppConfig, ModelDefinition } from '@/types/config';

export const useConfigStore = defineStore('config', () => {
  const models = ref<ModelDefinition[]>([]);
  const apiKeyRequired = ref(false);
  const desktopColumns = ref(4);
  const loaded = ref(false);

  const isReady = computed(() => loaded.value && models.value.length > 0);

  function initFromConfig(config: AppConfig): void {
    models.value = config.models;
    apiKeyRequired.value = config.apiKeyRequired;
    desktopColumns.value = config.layout.desktopColumns;
    loaded.value = true;
  }

  function reset(): void {
    models.value = [];
    apiKeyRequired.value = false;
    desktopColumns.value = 4;
    loaded.value = false;
  }

  return {
    models,
    apiKeyRequired,
    desktopColumns,
    loaded,
    isReady,
    initFromConfig,
    reset,
  };
});
