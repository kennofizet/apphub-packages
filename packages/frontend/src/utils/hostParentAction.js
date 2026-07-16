/** postMessage channel for Hub ↔ product shell host config / chrome actions. */
export const APPHUB_HOST_CHANNEL = 'apphub:host'

/**
 * Notify the embedding product parent that the user triggered a Hub chrome action
 * (e.g. shutdown). Parent listens on channel `apphub:host`, type `action`.
 *
 * @param {string} action
 * @param {{ productOrigin?: string }} [options]
 * @returns {boolean} true if a message was posted
 */
export function notifyHostParentAction(action, options = {}) {
  const name = typeof action === 'string' ? action.trim() : ''
  if (!name) return false
  if (typeof window === 'undefined' || window.parent === window) return false

  const origin = typeof options.productOrigin === 'string' ? options.productOrigin.trim() : ''
  const targetOrigin = origin || '*'

  window.parent.postMessage(
    {
      channel: APPHUB_HOST_CHANNEL,
      type: 'action',
      action: name,
    },
    targetOrigin,
  )

  return true
}

/**
 * Normalize install / host-config `shutdownAction`.
 * @param {unknown} value
 * @returns {string} empty when disabled
 */
export function normalizeShutdownAction(value) {
  if (value === true || value === 1 || value === '1') return 'shutdown'
  if (typeof value !== 'string') return ''
  return value.trim()
}
