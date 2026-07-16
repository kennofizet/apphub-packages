import { inject } from 'vue'
import { APPHUB_DEVICE_MODE_KEY } from '../constants.js'
import { createDeviceModeState } from './createDeviceModeState.js'

/**
 * Provide device mode on the Vue app (installAppHubModule).
 * @param {import('vue').App} vueApp
 * @param {ReturnType<typeof createDeviceModeState>} [deviceMode]
 */
export function provideDeviceMode(vueApp, deviceMode) {
  const state = deviceMode ?? createDeviceModeState()
  state.start()
  vueApp.provide(APPHUB_DEVICE_MODE_KEY, state)
  return state
}

/**
 * Inject device mode from installAppHubModule.
 * Falls back to a local listener if provide is missing (tests / isolated mount).
 */
export function useDeviceMode() {
  const injected = inject(APPHUB_DEVICE_MODE_KEY, null)
  if (injected) return injected

  const local = createDeviceModeState()
  local.start()
  return local
}
