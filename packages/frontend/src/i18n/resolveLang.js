/** Normalize language from prop, inject, or ref. */
export function resolveLang(source, fallback = 'vi') {
  if (typeof source === 'string' && source) return source
  if (source && typeof source.value === 'string' && source.value) return source.value
  return fallback
}
