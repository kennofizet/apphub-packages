import { computed, reactive } from 'vue'
import { detectDeviceSnapshot } from '../detectDevice.js'
import { resolveDeviceProfile } from '../registry.js'

/**
 * Reactive device mode state (pc | mobile + phone profile).
 * Listens to viewport resize; phone profile from UA when mobile.
 */
export function createDeviceModeState(options = {}) {
  const getViewport = typeof options.getViewport === 'function'
    ? options.getViewport
    : () => ({
      width: typeof window !== 'undefined' ? window.innerWidth : 1280,
      height: typeof window !== 'undefined' ? window.innerHeight : 800,
    })

  const initial = detectDeviceSnapshot(getViewport())
  const state = reactive({
    mode: initial.mode,
    phone: initial.phone,
    width: initial.width,
    height: initial.height,
    profile: resolveDeviceProfile(initial),
  })

  let listening = false
  let frame = 0

  function applySnapshot(snapshot) {
    state.mode = snapshot.mode
    state.phone = snapshot.phone
    state.width = snapshot.width
    state.height = snapshot.height
    state.profile = resolveDeviceProfile(snapshot)
  }

  function refresh() {
    applySnapshot(detectDeviceSnapshot(getViewport()))
  }

  function onResize() {
    if (typeof window === 'undefined') return
    if (frame) cancelAnimationFrame(frame)
    frame = requestAnimationFrame(() => {
      frame = 0
      refresh()
    })
  }

  function start() {
    if (listening || typeof window === 'undefined') return
    listening = true
    refresh()
    window.addEventListener('resize', onResize, { passive: true })
  }

  function stop() {
    if (!listening || typeof window === 'undefined') return
    listening = false
    window.removeEventListener('resize', onResize)
    if (frame) {
      cancelAnimationFrame(frame)
      frame = 0
    }
  }

  /**
   * Run a device/phone action by name (phone overrides mode).
   * @param {string} name
   * @param {...unknown} args
   */
  function runAction(name, ...args) {
    const fn = state.profile?.actions?.[name]
    if (typeof fn !== 'function') return undefined
    return fn(...args)
  }

  return {
    state,
    refresh,
    start,
    stop,
    runAction,
    isMobile: computed(() => state.mode === 'mobile'),
    isPc: computed(() => state.mode === 'pc'),
  }
}
