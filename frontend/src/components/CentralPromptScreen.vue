<script setup lang="ts">
import { ref } from 'vue';

defineProps<{
  error?: string | null;
}>();

const prompt = ref('');
const emit = defineEmits<{
  submit: [prompt: string];
}>();

function handleSubmit(): void {
  const trimmed = prompt.value.trim();
  if (!trimmed) return;
  emit('submit', trimmed);
  prompt.value = '';
}

function handleKeydown(e: KeyboardEvent): void {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    handleSubmit();
  }
}
</script>

<template>
  <div class="flex h-screen items-center justify-center bg-white">
    <div class="w-full max-w-2xl px-6">
      <h1 class="mb-8 text-center text-3xl font-light text-gray-800">Consilium</h1>

      <div class="relative">
        <textarea
          v-model="prompt"
          class="w-full resize-none rounded-2xl border border-gray-200 bg-gray-50 px-5 py-4 text-base text-gray-800 shadow-sm transition-shadow placeholder:text-gray-400 focus:border-purple-300 focus:outline-none focus:ring-2 focus:ring-purple-100"
          rows="3"
          placeholder="Ask anything…"
          @keydown="handleKeydown"
        />

        <button
          class="absolute bottom-3 right-3 flex h-9 w-9 items-center justify-center rounded-xl bg-purple-600 text-white transition-colors hover:bg-purple-700 disabled:opacity-40"
          :disabled="!prompt.trim()"
          @click="handleSubmit"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"
            />
          </svg>
        </button>
      </div>

      <p class="mt-3 text-center text-xs text-gray-400">
        Press Enter to send · Shift+Enter for new line
      </p>

      <p v-if="error" class="mt-2 text-center text-sm text-red-500">
        {{ error }}
      </p>
    </div>
  </div>
</template>
