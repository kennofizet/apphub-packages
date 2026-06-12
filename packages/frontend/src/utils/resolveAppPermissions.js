import { isValidBridgeScope } from './appBridgeScopes.js'

/**
 * @param {unknown} app
 * @returns {string[]}
 */
export function resolveAppPermissions(app) {
  if (!app || typeof app !== 'object') return []

  const raw = /** @type {{ permissions?: unknown }} */ (app).permissions
  if (!Array.isArray(raw)) return []

  const scopes = []
  for (const item of raw) {
    let scope = null
    if (typeof item === 'string') {
      scope = item.trim()
    } else if (item && typeof item === 'object' && typeof item.scope === 'string') {
      scope = item.scope.trim()
    }
    if (!scope || !isValidBridgeScope(scope) || scopes.includes(scope)) continue
    scopes.push(scope)
  }

  return scopes
}
