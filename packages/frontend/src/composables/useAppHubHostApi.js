import { getCurrentInstance } from 'vue'
import { getAppHubStore } from '../moduleStore.js'

/**
 * Host-only API — use in Hub shell components. Not provided via inject
 * so publisher app code cannot access grantBridgeScope or internal docs.
 */
export function useAppHubHostApi() {
  const instance = getCurrentInstance()
  if (!instance) return null
  return getAppHubStore(instance.app)?.facade ?? null
}
