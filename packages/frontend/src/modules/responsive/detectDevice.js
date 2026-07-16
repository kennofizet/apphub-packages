import {
  DEVICE_MOBILE,
  DEVICE_PC,
  MOBILE_MAX_SHORT_EDGE,
  MOBILE_MAX_WIDTH,
  PHONE_ANDROID,
  PHONE_DEFAULT,
  PHONE_IPHONE,
} from './constants.js'

/**
 * @param {{ width?: number, height?: number }} [viewport]
 * @returns {'pc'|'mobile'}
 */
export function detectDeviceMode(viewport = {}) {
  const width = Number(viewport.width)
  const height = Number(viewport.height)
  const w = Number.isFinite(width) && width > 0
    ? width
    : (typeof window !== 'undefined' ? window.innerWidth : MOBILE_MAX_WIDTH + 1)
  const h = Number.isFinite(height) && height > 0
    ? height
    : (typeof window !== 'undefined' ? window.innerHeight : MOBILE_MAX_WIDTH + 1)

  const shortEdge = Math.min(w, h)
  if (w <= MOBILE_MAX_WIDTH || shortEdge <= MOBILE_MAX_SHORT_EDGE) {
    return DEVICE_MOBILE
  }

  return DEVICE_PC
}

/**
 * Phone profile under mobile mode. Current: light UA hints; unknown → _default.
 * Add folders under profiles/mobile/phones/{id} and map them here to scale.
 *
 * @param {{ userAgent?: string, mode?: string }} [options]
 * @returns {string}
 */
export function detectPhoneProfile(options = {}) {
  const mode = options.mode ?? detectDeviceMode()
  if (mode !== DEVICE_MOBILE) {
    return ''
  }

  const ua = String(
    options.userAgent
      ?? (typeof navigator !== 'undefined' ? navigator.userAgent : ''),
  ).toLowerCase()

  if (/iphone|ipod|ipad/.test(ua)) return PHONE_IPHONE
  if (/android/.test(ua)) return PHONE_ANDROID

  return PHONE_DEFAULT
}

/**
 * Full snapshot used by registry + UI bindings.
 *
 * @param {{ width?: number, height?: number, userAgent?: string }} [viewport]
 * @returns {{
 *   mode: 'pc'|'mobile',
 *   phone: string,
 *   width: number,
 *   height: number,
 *   isMobile: boolean,
 *   isPc: boolean,
 * }}
 */
export function detectDeviceSnapshot(viewport = {}) {
  const width = Number(viewport.width) > 0
    ? Number(viewport.width)
    : (typeof window !== 'undefined' ? window.innerWidth : 1280)
  const height = Number(viewport.height) > 0
    ? Number(viewport.height)
    : (typeof window !== 'undefined' ? window.innerHeight : 800)

  const mode = detectDeviceMode({ width, height })
  const phone = detectPhoneProfile({
    mode,
    userAgent: viewport.userAgent,
  })

  return {
    mode,
    phone,
    width,
    height,
    isMobile: mode === DEVICE_MOBILE,
    isPc: mode === DEVICE_PC,
  }
}
