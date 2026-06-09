import { onMounted, onUnmounted, ref, watch } from 'vue'

/**
 * Load next catalog page when the scroll root nears the bottom.
 */
export function useCatalogInfiniteScroll(options) {
  const rootRef = ref(null)
  const sentinelRef = ref(null)
  let observer = null

  function disconnect() {
    observer?.disconnect()
    observer = null
  }

  function connect() {
    disconnect()
    const root = rootRef.value
    const sentinel = sentinelRef.value
    if (!root || !sentinel || !options.canLoadMore?.()) return

    observer = new IntersectionObserver(
      (entries) => {
        if (entries.some((e) => e.isIntersecting)) {
          options.onLoadMore?.()
        }
      },
      { root, rootMargin: '120px', threshold: 0 },
    )
    observer.observe(sentinel)
  }

  onMounted(() => {
    connect()
  })

  onUnmounted(() => {
    disconnect()
  })

  watch(
    () => [options.canLoadMore?.(), rootRef.value, sentinelRef.value],
    () => connect(),
  )

  return { rootRef, sentinelRef, reconnect: connect }
}
