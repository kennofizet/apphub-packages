import { isValidBridgeScope } from './appBridgeScopes.js'

const SLUG_RE = /^[a-z0-9][a-z0-9_-]{0,63}$/
const MAX_STRING = 200
const MAX_HINT = 500
const MAX_ICON = 32
const MAX_LIST = 200

export function isValidSlug(slug) {
  return typeof slug === 'string' && SLUG_RE.test(slug)
}

export function clampString(value, max = MAX_STRING) {
  if (typeof value !== 'string') return ''
  return value.slice(0, max)
}

export function safeParseJson(raw, maxBytes = 512 * 1024) {
  if (!raw || typeof raw !== 'string' || raw.length > maxBytes) return null
  let parsed
  try {
    parsed = JSON.parse(raw)
  } catch {
    return null
  }
  if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return null
  if (hasDangerousOwnKey(parsed)) return null
  return parsed
}

function hasDangerousOwnKey(obj) {
  const dangerous = ['__proto__', 'constructor', 'prototype']
  return dangerous.some((key) => Object.prototype.hasOwnProperty.call(obj, key))
}

export function sanitizeGroupNames(value) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return {}
  const out = {}
  let count = 0
  for (const [key, name] of Object.entries(value)) {
    if (count >= MAX_LIST) break
    const id = clampString(key, 64)
    if (!id) continue
    out[id] = clampString(name, MAX_STRING)
    count += 1
  }
  return out
}

export function sanitizeDesktopSettings(parsed) {
  if (!parsed || typeof parsed !== 'object') return null
  const theme = parsed.theme
  return {
    snapToGrid: typeof parsed.snapToGrid === 'boolean' ? parsed.snapToGrid : undefined,
    theme: theme === 'dark' || theme === 'light' || theme === 'auto' ? theme : undefined,
    groupNames: sanitizeGroupNames(parsed.groupNames),
    builtinPositions: sanitizeBuiltinPlacements(parsed.builtinPositions),
    mobileDockIds: sanitizeMobileDockIds(parsed.mobileDockIds),
  }
}

function sanitizeMobileDockIds(value) {
  if (!Array.isArray(value)) return undefined
  const out = []
  for (const item of value) {
    const id = clampString(item, 80)
    if (!id || out.includes(id)) continue
    out.push(id)
    if (out.length >= 5) break
  }
  return out
}

function sanitizeBuiltinPlacements(value) {
  if (!value || typeof value !== 'object' || Array.isArray(value)) return undefined
  const out = {}
  let count = 0
  for (const [key, pos] of Object.entries(value)) {
    if (count >= MAX_LIST) break
    const id = clampString(key, 64)
    if (!id || !pos || typeof pos !== 'object') continue
    const x = Number(pos.x)
    const y = Number(pos.y)
    if (!Number.isFinite(x) || !Number.isFinite(y)) continue
    out[id] = { x: Math.round(x), y: Math.round(y) }
    count += 1
  }
  return Object.keys(out).length ? out : undefined
}

export function sanitizeUserApp(app) {
  if (!app || typeof app !== 'object') return null
  const slug = isValidSlug(app.slug)
    ? app.slug
    : (isValidSlug(app.id?.replace(/^user-/, '')) ? app.id.replace(/^user-/, '') : null)
  if (!slug) return null

  const installMethod = app.installMethod === 'local' || app.installMethod === 'appstore' || app.installMethod === 'publish'
    ? app.installMethod
    : (app.local ? 'local' : 'appstore')

  const status = app.status === 'draft' || app.status === 'active' || app.status === 'disabled'
    ? app.status
    : 'active'

  const desktopX = Number(app.desktopX)
  const desktopY = Number(app.desktopY)

  return {
    id: `user-${slug}`,
    slug,
    installedVersion: typeof app.installedVersion === 'string'
      ? clampString(app.installedVersion, 64) || null
      : (typeof app.version === 'string' ? clampString(app.version, 64) || null : null),
    version: typeof app.installedVersion === 'string'
      ? clampString(app.installedVersion, 64) || null
      : (typeof app.version === 'string' ? clampString(app.version, 64) || null : null),
    name: clampString(app.name) || slug,
    icon: clampString(app.icon, MAX_ICON) || '📦',
    hint: clampString(app.hint ?? app.description, MAX_HINT),
    status,
    runtime_type: app.runtime_type === 'iframe' || app.runtime_type === 'hosted' || app.runtime_type === 'connected' || app.runtime_type === 'native'
      ? app.runtime_type
      : 'iframe',
    entry_url: typeof app.entry_url === 'string' ? clampString(app.entry_url, 2048) || null : null,
    healthcheck_url: typeof app.healthcheck_url === 'string' ? clampString(app.healthcheck_url, 2048) || null : null,
    builtin: false,
    local: installMethod === 'local',
    installMethod,
    createdAt: clampString(app.createdAt, 40) || new Date().toISOString(),
    windowTitle: clampString(app.windowTitle ?? app.name) || slug,
    width: clampNumber(app.width, 320, 4096, 640),
    height: clampNumber(app.height, 240, 4096, 420),
    desktopX: Number.isFinite(desktopX) ? Math.round(desktopX) : null,
    desktopY: Number.isFinite(desktopY) ? Math.round(desktopY) : null,
    pending_version: typeof app.pending_version === 'string'
      ? clampString(app.pending_version, 64) || null
      : null,
    catalog_version: typeof app.catalog_version === 'string'
      ? clampString(app.catalog_version, 64) || null
      : null,
    rejected_version: typeof app.rejected_version === 'string'
      ? clampString(app.rejected_version, 64) || null
      : null,
    permissions: sanitizePermissions(app.permissions),
  }
}

function sanitizePermissions(raw) {
  if (!Array.isArray(raw)) return []
  const out = []
  for (const item of raw) {
    const scope = typeof item === 'string' ? item.trim() : ''
    if (!scope || !isValidBridgeScope(scope) || out.includes(scope)) continue
    out.push(scope)
  }
  return out
}

function clampNumber(value, min, max, fallback) {
  const n = Number(value)
  if (!Number.isFinite(n)) return fallback
  return Math.min(max, Math.max(min, Math.round(n)))
}

export function sanitizeWindowState(win) {
  if (!win || typeof win !== 'object') return null
  const appId = clampString(win.appId, 80)
  if (!appId) return null
  const display = win.display === 'mini' || win.display === 'fullscreen' ? win.display : 'mini'
  return {
    appId,
    minimized: !!win.minimized,
    display,
    x: clampNumber(win.x, 0, 100000, 0),
    y: clampNumber(win.y, 0, 100000, 0),
    width: clampNumber(win.width, 200, 10000, 720),
    height: clampNumber(win.height, 200, 10000, 480),
    zIndex: clampNumber(win.zIndex, 0, 100000, 1),
  }
}

export function sanitizeDesktopSession(parsed) {
  if (!parsed || typeof parsed !== 'object') return null

  const userApps = Array.isArray(parsed.userApps)
    ? parsed.userApps.slice(0, MAX_LIST).map(sanitizeUserApp).filter(Boolean)
    : []

  const installedSlugs = Array.isArray(parsed.installedSlugs)
    ? [...new Set(parsed.installedSlugs.filter(isValidSlug))].slice(0, MAX_LIST)
    : []

  const windows = Array.isArray(parsed.windows)
    ? parsed.windows.slice(0, MAX_LIST).map(sanitizeWindowState).filter(Boolean)
    : []

  const settings = sanitizeDesktopSettings(parsed.settings)
  const activeId = typeof parsed.activeId === 'string' ? clampString(parsed.activeId, 80) : null

  return {
    userApps,
    installedSlugs,
    windows,
    activeId: activeId || null,
    settings: settings ?? undefined,
  }
}

export function sanitizeWindowLayout(parsed) {
  if (!parsed || typeof parsed !== 'object') return null
  const display = parsed.display === 'mini' || parsed.display === 'fullscreen' ? parsed.display : null
  if (!display) return null

  const mini = parsed.mini ?? parsed
  if (!mini || typeof mini !== 'object') return { display }

  return {
    display,
    mini: {
      x: mini.x == null ? null : clampNumber(mini.x, 0, 100000, 0),
      y: mini.y == null ? null : clampNumber(mini.y, 0, 100000, 0),
      width: clampNumber(mini.width, 200, 10000, 720),
      height: clampNumber(mini.height, 200, 10000, 480),
    },
  }
}
