const IMAGE_FILENAME = /\.(png|svg|jpe?g|webp)$/i

/**
 * @param {{ icon?: string, icon_url?: string|null }} app
 */
export function resolveCatalogIconEmoji(app) {
  const icon = typeof app?.icon === 'string' && app.icon.trim() ? app.icon.trim() : '📦'
  if (IMAGE_FILENAME.test(icon)) return '📦'
  return icon
}

/**
 * @param {{ icon_url?: string|null }} app
 * @param {string} [apiBase]
 */
export function resolveCatalogIconSrc(app, apiBase = '') {
  const raw = typeof app?.icon_url === 'string' ? app.icon_url.trim() : ''
  if (!raw) return null
  if (/^https?:\/\//i.test(raw)) return raw
  const base = String(apiBase || '').replace(/\/$/, '')
  if (!base) return null
  return `${base}/${raw.replace(/^\//, '')}`
}
