import { ref, onMounted, onUnmounted } from 'vue'

const DESKTOP_MIN_WIDTH = 1200

export function useDesktop(minWidth = DESKTOP_MIN_WIDTH) {
  const isDesktop = ref(true)

  function update(): void {
    isDesktop.value = window.innerWidth >= minWidth
  }

  onMounted(() => {
    update()
    window.addEventListener('resize', update)
  })

  onUnmounted(() => {
    window.removeEventListener('resize', update)
  })

  return { isDesktop }
}
