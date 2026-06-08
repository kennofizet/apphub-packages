const VALID = new Set(['dark', 'light'])

/** Normalize theme from prop, inject, ref, or saved setting. Returns null when unset/auto. */
export function resolveTheme(source, fallback = null) {
  const read = (value) => {
    if (typeof value !== 'string') return null
    const code = value.trim().toLowerCase()
    if (code === 'auto' || code === '') return null
    return VALID.has(code) ? code : null
  }

  const direct = read(typeof source === 'string' ? source : null)
  if (direct) return direct

  if (source && typeof source === 'object' && 'value' in source) {
    const fromRef = read(source.value)
    if (fromRef) return fromRef
  }

  return read(fallback)
}

/** True when host passes theme via prop or installAppHubModule (not auto). */
export function isThemeLocked(propTheme, injectTheme) {
  return resolveTheme(propTheme) != null || resolveTheme(injectTheme) != null
}

export function normalizeTheme(value, fallback = 'dark') {
  return resolveTheme(value, fallback) ?? fallback
}
