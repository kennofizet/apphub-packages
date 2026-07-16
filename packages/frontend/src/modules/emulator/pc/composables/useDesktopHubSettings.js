import { computed, inject } from 'vue'
import { toValue } from '../../../../utils/toValue.js'

export const DESKTOP_HUB_SETTINGS_KEY = 'apphubDesktopHubSettings'

export function useDesktopHubSettings() {
  const hub = inject(DESKTOP_HUB_SETTINGS_KEY, null)
  if (!hub) {
    throw new Error('useDesktopHubSettings() must be used inside AppHubDesktop')
  }
  return hub
}

/** Catalog apps — reactive across install, rename, and session restore. */
export function useHubCatalogApps(hub = null) {
  const ctx = hub ?? useDesktopHubSettings()
  return computed(() => {
    const apps = toValue(ctx.desktopApps)
    if (Array.isArray(apps)) return apps
    return ctx.getDesktopApps?.() ?? []
  })
}

function buildPinFavoriteRows(catalog, store, visibleKey = 'visible') {
  const byId = new Map(catalog.map((app) => [app.id, app]))
  return Object.keys(store)
    .map((id) => {
      const app = byId.get(id)
      if (!app) return null
      return {
        id: app.id,
        name: app.name,
        icon: app.icon ?? '📦',
        visible: store[id]?.[visibleKey] !== false,
      }
    })
    .filter(Boolean)
}

/** Pinned apps for settings — tracks add/remove and visibility toggles live. */
export function useHubPinnedRows(hub = null) {
  const ctx = hub ?? useDesktopHubSettings()
  const catalog = useHubCatalogApps(ctx)
  return computed(() => buildPinFavoriteRows(catalog.value, ctx.startMenuPins ?? {}))
}

/** Favorite apps for settings — tracks add/remove and visibility toggles live. */
export function useHubFavoriteRows(hub = null) {
  const ctx = hub ?? useDesktopHubSettings()
  const catalog = useHubCatalogApps(ctx)
  return computed(() => buildPinFavoriteRows(catalog.value, ctx.startMenuFavorites ?? {}))
}
