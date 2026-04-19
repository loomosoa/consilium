<script setup lang="ts">
import { computed, ref } from 'vue';
import type { ColumnState } from '@/types/workspace';
import MessageBubble from '@/components/MessageBubble.vue';

const props = defineProps<{
  column: ColumnState;
}>();

const emit = defineEmits<{
  submit: [columnId: string, prompt: string];
  cancel: [columnId: string];
  retry: [columnId: string];
}>();

const followUpText = ref('');

const isStreaming = computed(() => props.column.status === 'streaming');
const isWaiting = computed(() => props.column.status === 'waiting');
const isError = computed(() => props.column.status === 'error');
const canSend = computed(() => !isStreaming.value && !isWaiting.value);

function handleSubmit(): void {
  const trimmed = followUpText.value.trim();
  if (!trimmed || !canSend.value) return;
  emit('submit', props.column.id, trimmed);
  followUpText.value = '';
}

function handleKeydown(e: KeyboardEvent): void {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    handleSubmit();
  }
}

function handleCancel(): void {
  emit('cancel', props.column.id);
}

function handleRetry(): void {
  emit('retry', props.column.id);
}
</script>

<template>
  <section
    class="flex h-full flex-col"
    :aria-label="`${column.providerName} ${column.displayName} column`"
  >
    <!-- Header -->
    <header class="shrink-0 border-b border-gray-100 px-4 py-3">
      <span class="text-xs font-medium text-gray-400">
        {{ column.providerName }} · {{ column.displayName }}
      </span>
    </header>

    <!-- Messages area -->
    <div class="flex-1 overflow-y-auto px-4 py-3">
      <MessageBubble
        v-for="msg in column.messages"
        :key="msg.id"
        :message="msg"
      />

      <!-- Streaming text -->
      <div
        v-if="column.streamingText"
        class="prose prose-sm max-w-none text-gray-800"
      >
        <p>{{ column.streamingText }}</p>
      </div>

      <!-- Loader -->
      <div
        v-if="isWaiting"
        class="flex items-center gap-2 py-2"
      >
        <div class="flex gap-1">
          <span
            class="h-1.5 w-1.5 animate-bounce rounded-full bg-purple-400"
            style="animation-delay: 0ms"
          />
          <span
            class="h-1.5 w-1.5 animate-bounce rounded-full bg-purple-400"
            style="animation-delay: 150ms"
          />
          <span
            class="h-1.5 w-1.5 animate-bounce rounded-full bg-purple-400"
            style="animation-delay: 300ms"
          />
        </div>
        <span class="text-xs text-gray-400">Generating…</span>
      </div>

      <!-- Error -->
      <div
        v-if="isError"
        class="mt-2 rounded-lg bg-red-50 px-3 py-2"
      >
        <p class="text-sm text-red-600">
          {{ column.errorMessage }}
        </p>
        <button
          class="mt-2 text-sm font-medium text-purple-600 hover:text-purple-700"
          aria-label="Retry request"
          @click="handleRetry"
        >
          Повторить запрос
        </button>
      </div>
    </div>

    <!-- Input area -->
    <div class="shrink-0 border-t border-gray-100 px-4 py-3">
      <!-- Stop button (streaming/waiting) -->
      <button
        v-if="isStreaming || isWaiting"
        class="flex w-full items-center justify-center gap-2 rounded-xl bg-gray-100 px-4 py-2.5 text-sm font-medium text-gray-600 transition-colors hover:bg-gray-200"
        aria-label="Stop generation"
        @click="handleCancel"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="h-4 w-4"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <rect
            x="6"
            y="6"
            width="8"
            height="8"
            rx="1"
          />
        </svg>
        Стоп
      </button>

      <!-- Follow-up input (idle/completed/error/cancelled) -->
      <div
        v-else
        class="relative"
      >
        <textarea
          v-model="followUpText"
          class="w-full resize-none rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 pr-10 text-sm text-gray-800 placeholder:text-gray-400 focus:border-purple-300 focus:outline-none focus:ring-1 focus:ring-purple-100"
          rows="1"
          :placeholder="isError ? 'Retry or type a new prompt…' : 'Type a message…'"
          aria-label="Follow-up prompt input"
          @keydown="handleKeydown"
        />
        <button
          class="absolute bottom-2 right-2 flex h-7 w-7 items-center justify-center rounded-lg bg-purple-600 text-white transition-colors hover:bg-purple-700 disabled:opacity-40"
          :disabled="!followUpText.trim()"
          aria-label="Send prompt"
          @click="handleSubmit"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-3.5 w-3.5"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"
            />
          </svg>
        </button>
      </div>
    </div>
  </section>
</template>
