import { safeParseJson } from '../../../utils/safeStorage.js'
import {
  BUILTIN_APP_STORE_ID,
  BUILTIN_GUIDE_ID,
  BUILTIN_SETTINGS_ID,
} from '../data/builtinApps.js'

const STORAGE_KEY = 'apphub-start-menu-pins'
const MAX_PINS = 64

const DEFAULT_BUILTIN_PIN_IDS = [
  BUILTIN_APP_STORE_ID,
  BUILTIN_GUIDE_ID,
  BUILTIN_SETTINGS_ID,
]

function defaultPins() {
  const pins = {}
  for (const id of DEFAULT_BUILTIN_PIN_IDS) {
    pins[id] = { visible: true }
  }
  return pins
}

function sanitizePins(parsed) {
  if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return null
  const raw = parsed.pins ?? parsed
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null

  const pins = {}
  let count = 0
  for (const [key, value] of Object.entries(raw)) {
    if (count >= MAX_PINS) break
    const id = typeof key === 'string' ? key.slice(0, 80) : ''
    if (!id) continue
    pins[id] = {
      visible: value && typeof value === 'object' && value.visible === false ? false : true,
    }
    count += 1
  }
  return pins
}

export function loadStartMenuPins() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    const parsed = safeParseJson(raw, 16 * 1024)
    const pins = sanitizePins(parsed)
    if (pins && Object.keys(pins).length) return pins
  } catch {
    /* ignore */
  }
  return defaultPins()
}

export function saveStartMenuPins(pins) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ pins }))
  } catch {
    /* ignore */
  }
}

export function isAppPinned(pins, appId) {
  return !!(appId && pins && Object.prototype.hasOwnProperty.call(pins, appId))
}

export function isAppVisibleInStart(pins, appId) {
  if (!isAppPinned(pins, appId)) return false
  return pins[appId].visible !== false
}

export function pinApp(pins, appId) {
  if (!appId) return
  pins[appId] = { visible: true }
}

export function unpinApp(pins, appId) {
  if (!appId || !pins) return
  delete pins[appId]
}

export function setPinVisible(pins, appId, visible) {
  if (!isAppPinned(pins, appId)) return
  pins[appId].visible = !!visible
}

export function listPinnedAppIds(pins) {
  return Object.keys(pins ?? {})
}
