/** Internal inject key — package components only; not part of public host API. */
export const APPHUB_MODULE_STORE_KEY = Symbol('apphubModuleStore')

const REGISTRY_KEY = '__kennofizet_apphub_installed_apps__'

/** Shared WeakMap — Vite may load this module twice (host bundle vs SFC chunks). */
function getInstalledAppsRegistry() {
  const root = typeof globalThis !== 'undefined' ? globalThis : global
  if (!root[REGISTRY_KEY]) {
    root[REGISTRY_KEY] = new WeakMap()
  }
  return root[REGISTRY_KEY]
}

const installedApps = getInstalledAppsRegistry()

export function registerAppHubStore(app, store) {
  installedApps.set(app, store)
}

export function getAppHubStore(app) {
  return app != null ? installedApps.get(app) ?? null : null
}
