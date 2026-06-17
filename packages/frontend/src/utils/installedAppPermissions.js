import { isValidBridgeScope } from './appBridgeScopes.js'
import { isValidSlug, safeParseJson } from './safeStorage.js'

const STORAGE_KEY = 'apphub_installed_permissions'

/** @returns {Record<string, string[]>} */
function readStore() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (!raw) return {}
    const parsed = safeParseJson(raw, 256 * 1024)
    if (!parsed) return {}
    /** @type {Record<string, string[]>} */
    const out = {}
    for (const [slug, scopes] of Object.entries(parsed)) {
      if (!isValidSlug(slug) || !Array.isArray(scopes)) continue
      const normalized = normalizeScopes(scopes)
      if (normalized.length) out[slug] = normalized
    }
    return out
  } catch {
    return {}
  }
}

function writeStore(store) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(store))
  } catch {
    // ignore quota / private mode
  }
}

/** @param {unknown} scopes @returns {string[]} */
function normalizeScopes(scopes) {
  if (!Array.isArray(scopes)) return []
  const out = []
  for (const item of scopes) {
    const scope = typeof item === 'string' ? item.trim() : ''
    if (!scope || !isValidBridgeScope(scope) || out.includes(scope)) continue
    out.push(scope)
  }
  return out
}

/** @param {unknown} manifestScopes @returns {Set<string>} */
function manifestScopeSet(manifestScopes) {
  return new Set(normalizeScopes(manifestScopes))
}

/** @returns {string[]} */
export function getInstalledPermissions(slug) {
  if (!isValidSlug(slug)) return []
  return readStore()[slug] ?? []
}

/** Replace stored scopes with the set the user accepted at install/update. */
export function saveInstalledPermissions(slug, scopes) {
  if (!isValidSlug(slug)) return
  const next = normalizeScopes(scopes)
  const store = readStore()
  if (!next.length) {
    delete store[slug]
  } else {
    store[slug] = next
  }
  writeStore(store)
}

/** Persist a single runtime-consented scope (must stay within manifest allowlist). */
export function addInstalledPermission(slug, scope, manifestScopes = null) {
  if (!isValidSlug(slug) || !isValidBridgeScope(scope)) return
  const allowed = manifestScopes == null ? null : manifestScopeSet(manifestScopes)
  if (allowed !== null && !allowed.has(scope)) return

  const store = readStore()
  const existing = normalizeScopes(store[slug] ?? [])
  if (existing.includes(scope)) return
  store[slug] = [...existing, scope]
  writeStore(store)
}

export function hasInstalledPermission(slug, scope, manifestScopes = null) {
  if (!isValidSlug(slug) || !isValidBridgeScope(scope)) return false
  if (manifestScopes != null && !manifestScopeSet(manifestScopes).has(scope)) return false
  return getInstalledPermissions(slug).includes(scope)
}

/** Drop stored scopes that are no longer declared in the app manifest. */
export function reconcileInstalledPermissions(slug, manifestScopes) {
  if (!isValidSlug(slug)) return
  const allowed = manifestScopeSet(manifestScopes)
  const store = readStore()
  const current = normalizeScopes(store[slug] ?? [])
  const next = allowed.size ? current.filter((scope) => allowed.has(scope)) : []
  if (next.length) {
    store[slug] = next
  } else {
    delete store[slug]
  }
  writeStore(store)
}

export function clearInstalledPermissions(slug) {
  if (!isValidSlug(slug)) return
  const store = readStore()
  if (!(slug in store)) return
  delete store[slug]
  writeStore(store)
}
