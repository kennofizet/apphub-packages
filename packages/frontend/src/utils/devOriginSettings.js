const STORAGE_KEY = 'apphub-dev-friendly-origins'

/** Default true — relaxed origin checks on localhost until dev turns off. */
export function loadDevFriendlyOriginsPreference() {
  if (typeof localStorage === 'undefined') return true
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw === null) return true
    return raw !== '0' && raw !== 'false'
  } catch {
    return true
  }
}

export function saveDevFriendlyOriginsPreference(enabled) {
  if (typeof localStorage === 'undefined') return
  try {
    localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0')
  } catch {
    /* ignore */
  }
}
