/** @type {readonly string[]} */
export const APP_BRIDGE_SCOPES = Object.freeze([
  'user.read',
  'user.profile',
  'desktop.notify',
  'desktop.message',
  'desktop.badge',
  'desktop.download',
])

const SCOPE_SET = new Set(APP_BRIDGE_SCOPES)

export function isParentBridgeScope(scope) {
  return typeof scope === 'string' && /^parent\.[a-z0-9][a-z0-9._-]{0,62}$/.test(scope)
}

export function isValidBridgeScope(scope) {
  return typeof scope === 'string' && (SCOPE_SET.has(scope) || isParentBridgeScope(scope))
}

/** Fixed Hub core scopes only — parent.* labels come from host config (GET parent-bridge/scope-prompts). */
const BRIDGE_SCOPE_LABEL_KEYS = {
  'user.read': 'bridge_perm_user_read',
  'user.profile': 'bridge_perm_user_profile',
  'desktop.notify': 'bridge_perm_desktop_notify',
  'desktop.message': 'bridge_perm_desktop_message',
  'desktop.badge': 'bridge_perm_desktop_badge',
  'desktop.download': 'bridge_perm_desktop_download',
}

/**
 * @param {string} scope
 * @param {string} appLabel
 * @param {(key: string, params?: Record<string, string>) => string} translate
 * @param {{ parentScopeLabel?: (scope: string, appLabel: string) => string }} [options]
 */
export function bridgeScopeLabel(scope, appLabel, translate, options = {}) {
  if (isParentBridgeScope(scope) && typeof options.parentScopeLabel === 'function') {
    return options.parentScopeLabel(scope, appLabel)
  }

  const key = BRIDGE_SCOPE_LABEL_KEYS[scope] ?? 'bridge_perm_default'
  const template = translate(key)
  return template.replace(/\{app\}/g, appLabel).replace(/\{scope\}/g, scope)
}
