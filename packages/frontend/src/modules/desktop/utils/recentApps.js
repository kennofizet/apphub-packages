const STORAGE_KEY = 'apphub-recent-apps'
export const MAX_RECENT = 10
export const MAX_SUGGESTED = 6
const MAX_OPEN_LOG = 48
const MAX_OPEN_COUNT = 999_999

function clampOpenCount(value) {
  if (typeof value !== 'number' || !Number.isFinite(value) || value < 1) return 1
  return Math.min(Math.floor(value), MAX_OPEN_COUNT)
}

function clampOpenedAt(value, fallback = Date.now()) {
  if (typeof value === 'number' && Number.isFinite(value) && value > 0) return value
  return fallback
}

function sanitizeEntry(entry, fallbackOpenedAt = Date.now()) {
  if (typeof entry === 'string' && entry.length > 0) {
    return { id: entry.slice(0, 80), openCount: 1, openedAt: fallbackOpenedAt }
  }
  if (entry && typeof entry === 'object' && typeof entry.id === 'string' && entry.id.length > 0) {
    const openCount =
      typeof entry.openCount === 'number' ? clampOpenCount(entry.openCount) : 1
    const openedAt = clampOpenedAt(entry.openedAt, fallbackOpenedAt)
    return { id: entry.id.slice(0, 80), openCount, openedAt }
  }
  return null
}

function mergeEntries(a, b) {
  return {
    id: a.id,
    openCount: clampOpenCount(Math.max(a.openCount, b.openCount)),
    openedAt: Math.max(a.openedAt, b.openedAt),
  }
}

/** One entry per app with merged openCount and latest openedAt. */
export function normalizeOpenLog(log) {
  if (!Array.isArray(log) || !log.length) return []
  const byId = new Map()
  for (const entry of log) {
    const sanitized = sanitizeEntry(entry)
    if (!sanitized) continue
    const prev = byId.get(sanitized.id)
    byId.set(sanitized.id, prev ? mergeEntries(prev, sanitized) : sanitized)
  }
  return [...byId.values()]
}

/** Most recently opened first. */
export function sortOpenLogByTime(log) {
  return [...normalizeOpenLog(log)].sort((a, b) => b.openedAt - a.openedAt)
}

/** Most opened first; ties broken by most recent open. */
export function sortOpenLogByCount(log) {
  return [...normalizeOpenLog(log)].sort(
    (a, b) => b.openCount - a.openCount || b.openedAt - a.openedAt,
  )
}

export function loadRecentOpenLog() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw || typeof raw !== 'string' || raw.length > 16 * 1024) return []
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed)) return []

    const now = Date.now()
    if (parsed.length && typeof parsed[0] === 'string') {
      return normalizeOpenLog(
        parsed.map((id, index) => sanitizeEntry(id, now - index * 1000)),
      ).slice(0, MAX_OPEN_LOG)
    }

    return normalizeOpenLog(parsed.map((entry) => sanitizeEntry(entry))).slice(0, MAX_OPEN_LOG)
  } catch {
    return []
  }
}

export function saveRecentOpenLog(log) {
  try {
    const normalized = normalizeOpenLog(log).slice(0, MAX_OPEN_LOG)
    localStorage.setItem(STORAGE_KEY, JSON.stringify(normalized))
    return normalized
  } catch {
    return Array.isArray(log) ? log : []
  }
}

export function recordRecentApp(appId, currentLog = null) {
  if (!appId || typeof appId !== 'string') {
    return normalizeOpenLog(Array.isArray(currentLog) ? currentLog : loadRecentOpenLog())
  }
  const base = normalizeOpenLog(Array.isArray(currentLog) ? currentLog : loadRecentOpenLog())
  const existing = base.find((entry) => entry.id === appId)
  const now = Date.now()
  const bumped = {
    id: appId,
    openCount: (existing?.openCount ?? 0) + 1,
    openedAt: now,
  }
  const merged = [bumped, ...base.filter((entry) => entry.id !== appId)]
  return saveRecentOpenLog(merged)
}

export function getRecentAppIds(log = loadRecentOpenLog()) {
  return sortOpenLogByTime(log)
    .map((entry) => entry.id)
    .slice(0, MAX_RECENT)
}

/** @deprecated use loadRecentOpenLog */
export function loadRecentAppIds() {
  return getRecentAppIds()
}

export function resolveRecentApps(catalog, log = loadRecentOpenLog()) {
  if (!Array.isArray(catalog) || !catalog.length) return []
  const byId = new Map(catalog.map((app) => [app.id, app]))
  return sortOpenLogByTime(log)
    .map((entry) => byId.get(entry.id))
    .filter(Boolean)
    .slice(0, MAX_RECENT)
}

export function resolveSuggestedApps(catalog, log = loadRecentOpenLog(), options = {}) {
  const limit = options.limit ?? MAX_SUGGESTED
  const excludeIds = options.excludeIds ?? new Set()
  const includeBuiltins = options.includeBuiltins === true
  if (!Array.isArray(catalog) || !catalog.length || !Array.isArray(log) || !log.length) return []

  const byId = new Map(catalog.map((app) => [app.id, app]))
  const result = []

  for (const entry of sortOpenLogByCount(log)) {
    if (result.length >= limit) break
    if (excludeIds.has(entry.id)) continue
    const app = byId.get(entry.id)
    if (!app) continue
    if (!includeBuiltins && app.builtin) continue
    result.push(app)
  }

  return result
}
