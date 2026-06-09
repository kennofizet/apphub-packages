import { getCurrentInstance } from 'vue'
import { getAppHubStore } from '../moduleStore.js'

export function resolveRootApp(instance = getCurrentInstance()) {
  if (!instance) return null
  return instance.appContext?.app ?? instance.app ?? null
}

export function getHostApiForApp(app) {
  return getAppHubStore(app)?.facade ?? null
}

export function isBackendReadyForApp(app) {
  const store = getAppHubStore(app)
  return !!(store?.credentials?.backendUrl && store?.credentials?.token)
}

/**
 * Host-only API — use in Hub shell components. Not provided via inject
 * so publisher app code cannot access grantBridgeScope or internal docs.
 */
export function useAppHubHostApi() {
  return getHostApiForApp(resolveRootApp())
}
