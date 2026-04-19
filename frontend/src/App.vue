<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { appBootstrapService } from '@/services/bootstrap';
import { useConfigStore } from '@/stores/config';
import { useDesktop } from '@/composables/useDesktop';
import ApiKeyModal from '@/components/ApiKeyModal.vue';
import CentralPromptScreen from '@/components/CentralPromptScreen.vue';
import DesktopRequirementScreen from '@/components/DesktopRequirementScreen.vue';

const configStore = useConfigStore();
const { isDesktop } = useDesktop();

const bootstrapping = ref(true);
const bootstrapError = ref('');
const showApiKeyModal = ref(false);

type AppView = 'landing' | 'workspace';
const currentView = ref<AppView>('landing');

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

function onPromptSubmit(prompt: string): void {
  currentView.value = 'workspace';
  // TODO: Epic 13 — call POST /api/workspaces and start SSE streams
  console.log('Prompt submitted:', prompt);
}
</script>

<template>
  <DesktopRequirementScreen v-if="!isDesktop" />

  <template v-else>
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
      class="min-h-screen bg-white"
    >
      <Transition
        name="view"
        mode="out-in"
      >
        <CentralPromptScreen
          v-if="currentView === 'landing'"
          @submit="onPromptSubmit"
        />

        <div
          v-else
          class="flex h-screen"
        >
          <!-- TODO: Epic 12 — WorkspaceGrid with 4 ColumnPanels -->
          <p class="m-auto text-gray-400">
            Workspace — 4 columns placeholder
          </p>
        </div>
      </Transition>
    </main>

    <ApiKeyModal
      v-if="showApiKeyModal"
      @stored="onKeyStored"
      @cancel="showApiKeyModal = false"
    />
  </template>
</template>

<style scoped>
.view-enter-active {
  transition:
    opacity 0.3s ease,
    transform 0.3s ease;
}

.view-leave-active {
  transition:
    opacity 0.2s ease,
    transform 0.2s ease;
}

.view-enter-from {
  opacity: 0;
  transform: translateY(12px);
}

.view-leave-to {
  opacity: 0;
  transform: translateY(-12px);
}
</style>
