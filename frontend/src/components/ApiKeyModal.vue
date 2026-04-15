<script setup lang="ts">
import { ref } from 'vue'
import { api } from '@/services/api'

const emit = defineEmits<{
  stored: []
  cancel: []
}>()

const apiKey = ref('')
const error = ref('')
const loading = ref(false)

async function submit(): Promise<void> {
  if (!apiKey.value.trim()) {
    error.value = 'API key is required'
    return
  }

  loading.value = true
  error.value = ''

  try {
    await api.storeApiKey(apiKey.value.trim())
    apiKey.value = ''
    emit('stored')
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to store API key'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl dark:bg-gray-800">
      <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
        Enter OpenRouter API Key
      </h2>
      <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        An OpenRouter API key is required to use this application. Your key will be stored in the
        server session and never saved in browser storage.
      </p>

      <form @submit.prevent="submit">
        <input
          v-model="apiKey"
          type="password"
          placeholder="sk-..."
          autocomplete="off"
          class="mb-2 w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
          :disabled="loading"
        >

        <p
          v-if="error"
          class="mb-3 text-sm text-red-600 dark:text-red-400"
        >
          {{ error }}
        </p>

        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="rounded px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700"
            :disabled="loading"
            @click="$emit('cancel')"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="rounded bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-700 disabled:opacity-50"
            :disabled="loading"
          >
            {{ loading ? 'Saving...' : 'Save Key' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
