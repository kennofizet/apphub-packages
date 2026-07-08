/** Hub + child-side limits for parent bridge postMessage payloads. */
export const PARENT_BRIDGE_MAX_ARGS_BYTES = 65_536

const ACTION_NAME_PATTERN = /^[a-z][a-z0-9._-]{0,63}$/
const EVENT_NAME_PATTERN = /^[a-z][a-z0-9._:-]{0,63}$/
const BRIDGE_CALL_ID_PATTERN = /^bridge-[0-9]+-[a-z0-9]{2,12}$/i

export function encodedJsonSize(value) {
  try {
    return new TextEncoder().encode(JSON.stringify(value ?? {})).length
  } catch {
    return PARENT_BRIDGE_MAX_ARGS_BYTES + 1
  }
}

export function assertParentBridgePayloadSize(payload, maxBytes = PARENT_BRIDGE_MAX_ARGS_BYTES) {
  if (encodedJsonSize(payload) > maxBytes) {
    throw new Error('PAYLOAD_TOO_LARGE')
  }
}

export function isValidParentBridgeActionName(name) {
  return typeof name === 'string' && ACTION_NAME_PATTERN.test(name.trim())
}

export function isValidParentBridgeEventName(name) {
  return typeof name === 'string' && EVENT_NAME_PATTERN.test(name.trim())
}

export function isValidParentBridgeCallId(id) {
  return typeof id === 'string' && BRIDGE_CALL_ID_PATTERN.test(id)
}

/**
 * @param {string} productOrigin
 * @param {string[]|undefined} allowedOrigins from bootstrap allowed_product_origins
 */
export function isAllowedProductOrigin(productOrigin, allowedOrigins) {
  const origin = String(productOrigin ?? '').trim()
  if (!origin) return false
  if (!Array.isArray(allowedOrigins) || allowedOrigins.length === 0) {
    return true
  }
  return allowedOrigins.includes(origin)
}
