import { onBeforeUnmount, onMounted, ref } from 'vue'

export const WINDOW_FRAME_ACTIVATE_KEY = 'apphubWindowFrameActivate'

/**
 * Raise window on body interaction; track embedded iframes for click shield.
 * @param {import('vue').Ref<HTMLElement|null>} bodyRef
 * @param {() => void} onActivate
 */
export function useWindowFrameActivation(bodyRef, onActivate) {
  const bound = new WeakSet()
  const hasEmbeddedFrame = ref(false)

  function bindNode(node) {
    if (!node || bound.has(node)) return
    bound.add(node)
    node.addEventListener('pointerdown', onActivate, true)
    node.addEventListener('focus', onActivate, true)
  }

  function scan() {
    const root = bodyRef.value
    if (!root) {
      hasEmbeddedFrame.value = false
      return
    }
    bindNode(root)
    const frames = root.querySelectorAll('iframe')
    hasEmbeddedFrame.value = frames.length > 0
    frames.forEach((frame) => bindNode(frame))
  }

  /** @type {MutationObserver|null} */
  let observer = null

  onMounted(() => {
    scan()
    const root = bodyRef.value
    if (!root || typeof MutationObserver === 'undefined') return
    observer = new MutationObserver(() => scan())
    observer.observe(root, { childList: true, subtree: true })
  })

  onBeforeUnmount(() => {
    observer?.disconnect()
    observer = null
  })

  return { rescan: scan, hasEmbeddedFrame }
}
