<script setup lang="ts">
import { computed } from 'vue';
import MarkdownIt from 'markdown-it';
import type { ColumnMessage } from '@/types/workspace';

const props = defineProps<{
  message: ColumnMessage;
}>();

const md = new MarkdownIt({
  html: false,
  linkify: true,
  breaks: true,
});

const isUser = computed(() => props.message.role === 'user');
const renderedContent = computed(() => {
  if (isUser.value) {
    return props.message.content;
  }
  return md.render(props.message.content);
});
</script>

<template>
  <div
    class="mb-3"
    :class="isUser ? 'text-right' : 'text-left'"
  >
    <div
      class="inline-block max-w-full rounded-xl px-3 py-2 text-sm"
      :class="isUser ? 'bg-purple-50 text-purple-900' : 'text-gray-800'"
    >
      <div
        v-if="isUser"
        class="whitespace-pre-wrap"
      >
        {{ message.content }}
      </div>
      <!-- Safe: markdown-it configured with html:false -->
      <!-- eslint-disable vue/no-v-html -->
      <div
        v-else
        class="prose prose-sm max-w-none"
        v-html="renderedContent"
      />
      <!-- eslint-enable vue/no-v-html -->
    </div>
  </div>
</template>
