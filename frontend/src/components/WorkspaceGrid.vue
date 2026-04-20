<script setup lang="ts">
import { storeToRefs } from 'pinia';
import { useWorkspaceStore } from '@/stores/workspace';
import ColumnPanel from '@/components/ColumnPanel.vue';

const store = useWorkspaceStore();
const { columns } = storeToRefs(store);

const emit = defineEmits<{
  submit: [columnId: string, prompt: string];
  cancel: [columnId: string];
  retry: [columnId: string];
}>();
</script>

<template>
  <div class="grid h-screen grid-cols-4 divide-x divide-gray-100 bg-white">
    <ColumnPanel
      v-for="column in columns"
      :key="column.id"
      :column="column"
      @submit="(...args: [string, string]) => emit('submit', ...args)"
      @cancel="(id: string) => emit('cancel', id)"
      @retry="(id: string) => emit('retry', id)"
    />
  </div>
</template>
