<script setup lang="ts">
import { useDesktop } from '@/composables/useDesktop'

const { isDesktop } = useDesktop()
</script>

<template>
  <div
    v-if="!isDesktop"
    class="flex h-screen flex-col items-center justify-center gap-4 bg-white px-6 text-center"
  >
    <div class="text-5xl">
      🖥️
    </div>
    <h1 class="text-xl font-semibold text-gray-800">
      Desktop Required
    </h1>
    <p class="max-w-md text-gray-500">
      Consilium requires a screen width of at least 1200&nbsp;px.
      Please switch to a desktop browser or resize your window.
    </p>
  </div>
</template>
