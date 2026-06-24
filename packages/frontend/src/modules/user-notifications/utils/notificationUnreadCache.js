import { safeParseJson } from '../../../utils/safeStorage.js'

const STORAGE_KEY = 'apphub-notification-unread'

function readStore() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    const parsed = safeParseJson(raw, 64 * 1024)
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return {}
    return parsed
  } catch {
    return {}
  }
}

function writeStore(data) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data))
  } catch {
    /* ignore */
  }
}

export function loadCachedUnreadCount(cacheKey) {
  const key = String(cacheKey ?? '').trim()
  if (!key) return null

  const entry = readStore()[key]
  if (!entry || typeof entry !== 'object') return null

  const count = entry.count
  if (typeof count !== 'number' || !Number.isFinite(count) || count < 0) return null
  return Math.floor(count)
}

export function saveCachedUnreadCount(cacheKey, count) {
  const key = String(cacheKey ?? '').trim()
  if (!key) return
  if (typeof count !== 'number' || !Number.isFinite(count) || count < 0) return

  const store = readStore()
  store[key] = { count: Math.floor(count), updatedAt: Date.now() }
  writeStore(store)
}
