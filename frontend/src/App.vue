<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { appBootstrapService } from '@/services/bootstrap';
import { useConfigStore } from '@/stores/config';
import { useDesktop } from '@/composables/useDesktop';
import { useWorkspaceActions } from '@/composables/useWorkspaceActions';
import ApiKeyModal from '@/components/ApiKeyModal.vue';
import CentralPromptScreen from '@/components/CentralPromptScreen.vue';
import DesktopRequirementScreen from '@/components/DesktopRequirementScreen.vue';
import WorkspaceGrid from '@/components/WorkspaceGrid.vue';

const configStore = useConfigStore();
const { isDesktop } = useDesktop();
const {
  currentView,
  submitError,
  startWorkspace,
  sendFollowUp,
  cancelStream,
  retryStream,
  cleanup,
} = useWorkspaceActions();

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

onUnmounted(() => {
  cleanup();
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
  startWorkspace(prompt);
}
</script>

<template>
  <DesktopRequirementScreen v-if="!isDesktop" />

  <template v-else>
    <div v-if="bootstrapping" class="flex h-screen items-center justify-center">
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

    <main v-else class="min-h-screen bg-white">
      <Transition name="view" mode="out-in">
        <CentralPromptScreen
          v-if="currentView === 'landing'"
          :error="submitError"
          @submit="onPromptSubmit"
        />

        <div
          v-else-if="currentView === 'transitioning'"
          class="flex h-screen items-center justify-center"
        >
          <div class="flex flex-col items-center gap-3">
            <div class="h-10 w-10 animate-spin rounded-full border-b-2 border-purple-600" />
            <span class="text-sm text-gray-400">Starting workspace…</span>
          </div>
        </div>

        <WorkspaceGrid v-else @submit="sendFollowUp" @cancel="cancelStream" @retry="retryStream" />
      </Transition>
    </main>

    <ApiKeyModal v-if="showApiKeyModal" @stored="onKeyStored" @cancel="showApiKeyModal = false" />
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
