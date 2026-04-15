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

async function retryBootstrap(): Promise<void> {
  bootstrapping.value = true;
  bootstrapError.value = '';
  try {
    await appBootstrapService.bootstrap();
    showApiKeyModal.value = configStore.apiKeyRequired;
  } catch (e) {
    bootstrapError.value = e instanceof Error ? e.message : 'Bootstrap failed';
  } finally {
    bootstrapping.value = false;
  }
}
</script>

<template>
  <div
    v-if="bootstrapping"
    class="flex h-screen items-center justify-center"
  >
    <div class="h-12 w-12 animate-spin rounded-full border-b-2 border-purple-600" />
  </div>

  <div
    v-else-if="bootstrapError"
    class="flex h-screen flex-col items-center justify-center gap-4"
  >
    <p class="text-red-600">
      {{ bootstrapError }}
    </p>
    <button
      class="rounded bg-purple-600 px-4 py-2 text-white hover:bg-purple-700"
      @click="retryBootstrap"
    >
      Retry
    </button>
  </div>

  <main
    v-else
    class="min-h-screen bg-white dark:bg-gray-900"
  >
    <p class="p-4 text-gray-600 dark:text-gray-400">
      Consilium — ready
    </p>
  </main>

  <ApiKeyModal
    v-if="showApiKeyModal"
    @stored="onKeyStored"
    @cancel="showApiKeyModal = false"
  />
</template>
