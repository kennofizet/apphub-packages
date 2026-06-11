import { getCurrentInstance, inject } from 'vue'
import { APPHUB_MODULE_STORE_KEY, getAppHubStore } from '../moduleStore.js'

export function resolveRootApp(instance = getCurrentInstance()) {
  if (!instance) return null
  return instance.appContext?.app ?? instance.app ?? null
}

/** Resolve module store — inject first (always same instance), then WeakMap by Vue app. */
export function useAppHubModuleStore() {
  const fromInject = inject(APPHUB_MODULE_STORE_KEY, null)
  if (fromInject) return fromInject
  return getAppHubStore(resolveRootApp())
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
  return useAppHubModuleStore()?.facade ?? null
}

export function isBackendReadyFromStore(store) {
  return !!(store?.credentials?.backendUrl && store?.credentials?.token)
}
