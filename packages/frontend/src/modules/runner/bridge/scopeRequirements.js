/** Scopes that satisfy getUserInfo (either is enough). */
export const USER_INFO_SCOPES = Object.freeze(['user.read', 'user.profile'])

/** Required scope per bridge method (first entry used for auto-prompt). */
export const BRIDGE_METHOD_SCOPE = Object.freeze({
  getUserInfo: 'user.read',
  getProfile: 'user.profile',
  notify: 'desktop.notify',
  sendDesktopMessage: 'desktop.message',
  setTaskbarBadge: 'desktop.badge',
})

/**
 * @param {string} method
 * @returns {string|null}
 */
export function requiredScopeForMethod(method) {
  return BRIDGE_METHOD_SCOPE[method] ?? null
}

/**
 * @param {string} method
 * @param {Set<string>} grantedScopes
 * @returns {boolean}
 */
export function isMethodScopeGranted(method, grantedScopes) {
  if (method === 'getUserInfo') {
    return USER_INFO_SCOPES.some((scope) => grantedScopes.has(scope))
  }

  const scope = requiredScopeForMethod(method)
  return scope ? grantedScopes.has(scope) : false
}

/**
 * Scope to request when method needs consent.
 * @param {string} method
 * @returns {string|null}
 */
export function scopeToRequestForMethod(method) {
  return requiredScopeForMethod(method)
}
