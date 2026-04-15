<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { appBootstrapService } from '@/services/bootstrap';
import { useConfigStore } from '@/stores/config';
import ApiKeyModal from '@/components/ApiKeyModal.vue';

const configStore = useConfigStore();
const bootstrapping = ref(true);
const bootstrapError = ref('');
const showApiKeyModal = ref(false);

onMounted(async () => {
  try {
    await appBootstrapService.bootstrap();
    showApiKeyModal.value = configStore.apiKeyRequired;
  } catch (e) {
    bootstrapError.value = e instanceof Error ? e.message : 'Bootstrap failed';
  } finally {
    bootstrapping.value = false;
  }
});

function onKeyStored(): void {
  showApiKeyModal.value = false;
}
</script>

<template>
  <div v-if="bootstrapping" class="flex h-screen items-center justify-center">
    <p class="text-gray-500">Loading...</p>
  </div>

  <div v-else-if="bootstrapError" class="flex h-screen items-center justify-center">
    <p class="text-red-600">{{ bootstrapError }}</p>
  </div>

  <main v-else class="min-h-screen bg-white dark:bg-gray-900">
    <p class="p-4 text-gray-600 dark:text-gray-400">Consilium — ready</p>
  </main>

  <ApiKeyModal v-if="showApiKeyModal" @stored="onKeyStored" @cancel="showApiKeyModal = false" />
</template>
