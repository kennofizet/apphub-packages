import { safeParseJson } from '../../../../utils/safeStorage.js'
import { sortOpenLogByTime } from './recentApps.js'

const STORAGE_KEY = 'apphub-start-menu-favorites'
const MAX_FAVORITES = 32

export const START_MENU_LIST_MAX = 10
export const START_MENU_FAVORITES_MAX = 5

function sanitizeFavorites(parsed) {
  if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return null
  const raw = parsed.favorites ?? parsed
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null

  const favorites = {}
  let count = 0
  for (const [key, value] of Object.entries(raw)) {
    if (count >= MAX_FAVORITES) break
    const id = typeof key === 'string' ? key.slice(0, 80) : ''
    if (!id) continue
    favorites[id] = {
      visible: value && typeof value === 'object' && value.visible === false ? false : true,
    }
    count += 1
  }
  return favorites
}

export function loadStartMenuFavorites() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    const parsed = safeParseJson(raw, 16 * 1024)
    const favorites = sanitizeFavorites(parsed)
    if (favorites) return favorites
  } catch {
    /* ignore */
  }
  return {}
}

export function saveStartMenuFavorites(favorites) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ favorites }))
  } catch {
    /* ignore */
  }
}

export function isAppFavorite(favorites, appId) {
  return !!(appId && favorites && Object.prototype.hasOwnProperty.call(favorites, appId))
}

export function isAppVisibleAsFavorite(favorites, appId) {
  if (!isAppFavorite(favorites, appId)) return false
  return favorites[appId].visible !== false
}

export function favoriteApp(favorites, appId) {
  if (!appId) return
  favorites[appId] = { visible: true }
}

export function unfavoriteApp(favorites, appId) {
  if (!appId || !favorites) return
  delete favorites[appId]
}

export function setFavoriteVisible(favorites, appId, visible) {
  if (!isAppFavorite(favorites, appId)) return
  favorites[appId].visible = !!visible
}

export function listFavoriteAppIds(favorites) {
  return Object.keys(favorites ?? {})
}

export function resolveVisibleFavoriteApps(catalog, favorites) {
  if (!Array.isArray(catalog) || !catalog.length) return []
  const byId = new Map(catalog.map((app) => [app.id, app]))
  return listFavoriteAppIds(favorites)
    .filter((id) => isAppVisibleAsFavorite(favorites, id))
    .map((id) => byId.get(id))
    .filter(Boolean)
}

export function resolveStartMenuFavoriteApps(catalog, favorites) {
  return resolveVisibleFavoriteApps(catalog, favorites).slice(0, START_MENU_FAVORITES_MAX)
}

export function resolveStartMenuRecentApps(catalog, favorites, openLog) {
  const favoriteApps = resolveStartMenuFavoriteApps(catalog, favorites)
  const favoriteIdSet = new Set(favoriteApps.map((app) => app.id))
  const recentSlots = Math.max(0, START_MENU_LIST_MAX - favoriteApps.length)
  const byId = new Map((catalog ?? []).map((app) => [app.id, app]))
  return sortOpenLogByTime(openLog ?? [])
    .filter((entry) => !favoriteIdSet.has(entry.id))
    .slice(0, recentSlots)
    .map((entry) => byId.get(entry.id))
    .filter(Boolean)
}
