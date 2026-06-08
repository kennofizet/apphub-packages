/** Private per-Vue-app store — not exposed via provide/inject. */
const installedApps = new WeakMap()

export function registerAppHubStore(app, store) {
  installedApps.set(app, store)
}

export function getAppHubStore(app) {
  return app != null ? installedApps.get(app) ?? null : null
}
