import { safeParseJson, sanitizeDesktopSettings } from '../../../../utils/safeStorage.js'

const SETTINGS_KEY = 'apphub-desktop-settings'

const defaults = {
  snapToGrid: true,
  theme: 'dark',
  groupNames: {},
}

export function loadDesktopSettings() {
  try {
    const raw = localStorage.getItem(SETTINGS_KEY)
    const parsed = safeParseJson(raw, 64 * 1024)
    const sanitized = parsed ? sanitizeDesktopSettings(parsed) : null
    return { ...defaults, ...sanitized }
  } catch {
    return { ...defaults }
  }
}

export function applyDesktopSettings(target, source) {
  const sanitized = sanitizeDesktopSettings(source)
  if (!sanitized) return
  if (sanitized.snapToGrid !== undefined) target.snapToGrid = sanitized.snapToGrid
  if (sanitized.theme !== undefined) target.theme = sanitized.theme
  if (sanitized.groupNames) target.groupNames = sanitized.groupNames
  if (sanitized.builtinPositions) target.builtinPositions = sanitized.builtinPositions
}

export function saveDesktopSettings(settings) {
  try {
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings))
  } catch {
    /* ignore */
  }
}
