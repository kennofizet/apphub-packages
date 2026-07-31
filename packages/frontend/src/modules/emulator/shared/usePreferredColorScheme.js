import { onUnmounted, ref } from 'vue'

export function usePreferredColorScheme() {
  const query = typeof window !== 'undefined' && window.matchMedia
    ? window.matchMedia('(prefers-color-scheme: light)')
    : null
  const scheme = ref(query?.matches ? 'light' : 'dark')

  const onChange = (event) => {
    scheme.value = event.matches ? 'light' : 'dark'
  }
  query?.addEventListener?.('change', onChange)

  onUnmounted(() => query?.removeEventListener?.('change', onChange))

  return scheme
}
