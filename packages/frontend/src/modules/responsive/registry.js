import { DEVICE_MOBILE, DEVICE_PC, PHONE_DEFAULT } from './constants.js'
import pcProfile from './profiles/pc/index.js'
import mobileProfile from './profiles/mobile/index.js'
import phoneDefault from './profiles/mobile/phones/_default/index.js'
import phoneIphone from './profiles/mobile/phones/iphone/index.js'
import phoneAndroid from './profiles/mobile/phones/android/index.js'

/** @type {Record<string, { id: string, actions: Record<string, Function> }>} */
const MODE_PROFILES = {
  [DEVICE_PC]: pcProfile,
  [DEVICE_MOBILE]: mobileProfile,
}

/** @type {Record<string, { id: string, actions: Record<string, Function> }>} */
const PHONE_PROFILES = {
  [PHONE_DEFAULT]: phoneDefault,
  iphone: phoneIphone,
  android: phoneAndroid,
}

/**
 * Resolve mode profile + optional phone overlay.
 * Actions merge: mode defaults ← phone overrides (phone wins on key clash).
 *
 * @param {{ mode: string, phone?: string }} snapshot
 */
export function resolveDeviceProfile(snapshot) {
  const mode = snapshot?.mode === DEVICE_MOBILE ? DEVICE_MOBILE : DEVICE_PC
  const base = MODE_PROFILES[mode] ?? pcProfile

  let phone = null
  if (mode === DEVICE_MOBILE) {
    const phoneId = snapshot?.phone && PHONE_PROFILES[snapshot.phone]
      ? snapshot.phone
      : PHONE_DEFAULT
    phone = PHONE_PROFILES[phoneId] ?? phoneDefault
  }

  const actions = {
    ...(base.actions ?? {}),
    ...(phone?.actions ?? {}),
  }

  return {
    mode,
    phoneId: phone?.id ?? '',
    base,
    phone,
    actions,
  }
}

/**
 * Register a custom phone profile at runtime (host / future packages).
 * @param {string} id
 * @param {{ id?: string, actions?: Record<string, Function> }} profile
 */
export function registerPhoneProfile(id, profile) {
  const key = String(id ?? '').trim()
  if (!key || !profile || typeof profile !== 'object') return
  PHONE_PROFILES[key] = {
    id: profile.id ?? key,
    actions: profile.actions && typeof profile.actions === 'object' ? profile.actions : {},
  }
}

export function listPhoneProfileIds() {
  return Object.keys(PHONE_PROFILES)
}
