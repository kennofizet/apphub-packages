/**
 * @param {unknown} app
 * @returns {string[]}
 */
export function resolveAppApiUrls(app) {
  if (!app || typeof app !== 'object') return []

  const raw = /** @type {{ api_urls?: unknown, api_base_url?: unknown }} */ (app)
  const urls = []

  const list = raw.api_urls
  if (Array.isArray(list)) {
    for (const item of list) {
      const value = normalizeApiUrl(item)
      if (value && !urls.includes(value)) urls.push(value)
    }
  }

  const legacy = normalizeApiUrl(raw.api_base_url)
  if (legacy && !urls.includes(legacy)) urls.unshift(legacy)

  return urls
}

/** @param {unknown} value */
function normalizeApiUrl(value) {
  if (typeof value === 'string') {
    const trimmed = value.trim()
    return trimmed || null
  }
  if (value && typeof value === 'object' && typeof value.url === 'string') {
    const trimmed = value.url.trim()
    return trimmed || null
  }
  return null
}
