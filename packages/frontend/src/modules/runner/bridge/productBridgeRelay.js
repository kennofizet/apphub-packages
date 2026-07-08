import {
  PRODUCT_BRIDGE_CALL,
  PRODUCT_BRIDGE_CHANNEL,
  PRODUCT_BRIDGE_EVENT,
  PRODUCT_BRIDGE_RESULT,
} from './productBridgeConstants.js'
import {
  assertParentBridgePayloadSize,
  isAllowedProductOrigin,
  isValidParentBridgeCallId,
  PARENT_BRIDGE_MAX_ARGS_BYTES,
} from './parentBridgeSecurity.js'

/**
 * Hub-side relay: forward parent bridge RPC/events to product shell iframe parent.
 *
 * @param {{
 *   getProductOrigin?: () => string,
 *   getAllowedProductOrigins?: () => string[],
 *   getTimeoutMs?: () => number,
 *   getMaxArgsBytes?: () => number,
 * }} options
 */
export function createProductBridgeRelay(options) {
  const pending = new Map()
  let stopped = false

  function productOrigin() {
    const raw = options.getProductOrigin?.() ?? ''
    const trimmed = typeof raw === 'string' ? raw.trim() : ''
    return trimmed || null
  }

  function isProductOriginAllowed() {
    const origin = productOrigin()
    if (!origin) return false
    return isAllowedProductOrigin(origin, options.getAllowedProductOrigins?.())
  }

  function timeoutMs() {
    const value = Number(options.getTimeoutMs?.() ?? 30_000)
    return Number.isFinite(value) && value > 0 ? value : 30_000
  }

  function maxArgsBytes() {
    const value = Number(options.getMaxArgsBytes?.() ?? PARENT_BRIDGE_MAX_ARGS_BYTES)
    return Number.isFinite(value) && value > 0 ? value : PARENT_BRIDGE_MAX_ARGS_BYTES
  }

  function isTrustedProductSource(event) {
    const origin = productOrigin()
    if (!origin || !isProductOriginAllowed()) return false
    if (event.origin !== origin) return false
    if (window.parent === window) return false
    return event.source === window.parent
  }

  function postToProduct(message) {
    const origin = productOrigin()
    if (!origin || !isProductOriginAllowed() || window.parent === window) {
      throw new Error('PARENT_UNAVAILABLE')
    }
    window.parent.postMessage(message, origin)
  }

  /**
   * @param {string} id
   * @param {string} action
   * @param {unknown} args
   * @param {{
   *   app_slug?: string,
   *   session_id?: string|null,
   *   bridge_scope?: string,
   * }} meta
   */
  function forwardCall(id, action, args, meta) {
    if (!isValidParentBridgeCallId(id)) {
      return Promise.reject(new Error('INVALID_BRIDGE_ID'))
    }

    const normalizedArgs = args && typeof args === 'object' && !Array.isArray(args) ? args : {}
    assertParentBridgePayloadSize(normalizedArgs, maxArgsBytes())

    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => {
        pending.delete(id)
        reject(new Error('PARENT_TIMEOUT'))
      }, timeoutMs())

      pending.set(id, { resolve, reject, timer })

      try {
        postToProduct({
          channel: PRODUCT_BRIDGE_CHANNEL,
          type: PRODUCT_BRIDGE_CALL,
          id,
          action,
          args: normalizedArgs,
          app_slug: meta.app_slug ?? null,
          session_id: meta.session_id ?? null,
          bridge_scope: meta.bridge_scope ?? null,
        })
      } catch (err) {
        clearTimeout(timer)
        pending.delete(id)
        reject(err)
      }
    })
  }

  /**
   * @param {string} name
   * @param {unknown} payload
   * @param {{ app_slug?: string, bridge_scope?: string, session_id?: string|null }} meta
   */
  function forwardEvent(name, payload, meta) {
    const normalizedPayload = payload && typeof payload === 'object' && !Array.isArray(payload)
      ? payload
      : {}
    assertParentBridgePayloadSize(normalizedPayload, maxArgsBytes())

    postToProduct({
      channel: PRODUCT_BRIDGE_CHANNEL,
      type: PRODUCT_BRIDGE_EVENT,
      name,
      payload: normalizedPayload,
      app_slug: meta.app_slug ?? null,
      bridge_scope: meta.bridge_scope ?? null,
      session_id: meta.session_id ?? null,
    })
  }

  function onMessage(event) {
    if (stopped) return
    const data = event.data
    if (!data || data.channel !== PRODUCT_BRIDGE_CHANNEL) return
    if (!isTrustedProductSource(event)) return

    if (data.type !== PRODUCT_BRIDGE_RESULT || !data.id) return
    if (!isValidParentBridgeCallId(data.id)) return

    const entry = pending.get(data.id)
    if (!entry) return

    clearTimeout(entry.timer)
    pending.delete(data.id)

    if (data.ok) {
      entry.resolve(data.result)
      return
    }

    entry.reject(new Error(String(data.error || data.message || 'Bridge error')))
  }

  function start() {
    stopped = false
    window.addEventListener('message', onMessage)
  }

  function stop() {
    stopped = true
    window.removeEventListener('message', onMessage)
    for (const [id, entry] of pending.entries()) {
      clearTimeout(entry.timer)
      entry.reject(new Error('PARENT_UNAVAILABLE'))
      pending.delete(id)
    }
  }

  return {
    start,
    stop,
    forwardCall,
    forwardEvent,
    hasProductParent: () => Boolean(isProductOriginAllowed() && window.parent !== window),
  }
}
