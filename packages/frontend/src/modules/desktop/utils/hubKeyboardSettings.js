import { safeParseJson } from '../../../utils/safeStorage.js'

const STORAGE_KEY = 'apphub-keyboard-settings'

/** Browser-safe modifier — Windows (⊞)+arrow is captured by the OS, not the page. */
export const KEYBOARD_MODIFIER = 'ctrl-alt'

const defaults = {
  enabled: true,
  modifier: KEYBOARD_MODIFIER,
}

export function loadHubKeyboardSettings() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    const parsed = safeParseJson(raw, 8 * 1024)
    const merged = { ...defaults, ...sanitizeHubKeyboardSettings(parsed) }
    return normalizeHubKeyboardSettings(merged)
  } catch {
    return { ...defaults }
  }
}

export function sanitizeHubKeyboardSettings(parsed) {
  if (!parsed || typeof parsed !== 'object') return {}
  return {
    enabled: typeof parsed.enabled === 'boolean' ? parsed.enabled : undefined,
    modifier: parsed.modifier === KEYBOARD_MODIFIER ? KEYBOARD_MODIFIER : undefined,
  }
}

/** Coerce legacy Win-key preference saved before browser limitation was enforced. */
export function normalizeHubKeyboardSettings(settings) {
  return {
    enabled: settings.enabled !== false,
    modifier: KEYBOARD_MODIFIER,
  }
}

export function saveHubKeyboardSettings(settings) {
  try {
    const normalized = normalizeHubKeyboardSettings(settings)
    localStorage.setItem(STORAGE_KEY, JSON.stringify(normalized))
  } catch {
    /* ignore */
  }
}

/** @returns {'left'|'right'|'up'|'down'|null} */
export function matchSnapShortcut(event, settings) {
  if (!settings?.enabled) return null
  if (!event.ctrlKey || !event.altKey) return null
  if (event.metaKey) return null

  const map = {
    ArrowLeft: 'left',
    ArrowRight: 'right',
    ArrowUp: 'up',
    ArrowDown: 'down',
  }

  return map[event.key] ?? null
}
