import { normalizeDesktopThemeRequest } from './desktopThemePack.js'

const STORAGE_PREFIX = 'apphub-desktop-theme'

export function desktopThemeStorageKey(backendUrl, userId) {
  const backend = typeof backendUrl === 'string' ? backendUrl.trim().replace(/\/$/, '') : ''
  const user = userId == null ? '' : String(userId).trim()
  if (!backend || !user) return null
  return `${STORAGE_PREFIX}:${encodeURIComponent(backend)}:${encodeURIComponent(user)}`
}

export function loadDesktopThemePack(storage, key) {
  if (!storage || !key) return null
  try {
    const raw = storage.getItem(key)
    if (!raw || raw.length > 96 * 1024) return null
    const parsed = JSON.parse(raw)
    const normalized = normalizeDesktopThemeRequest(parsed)
    return normalized.mode === 'custom' ? normalized : null
  } catch {
    return null
  }
}

export function saveDesktopThemePack(storage, key, theme) {
  if (!storage || !key) return false
  const normalized = normalizeDesktopThemeRequest(theme)
  if (normalized.mode !== 'custom') {
    throw new Error('Only custom desktop theme packs can be persisted')
  }
  try {
    storage.setItem(key, JSON.stringify(normalized))
    return true
  } catch {
    return false
  }
}

export function clearDesktopThemePack(storage, key) {
  if (!storage || !key) return
  try {
    storage.removeItem(key)
  } catch {
    /* ignore unavailable storage */
  }
}
