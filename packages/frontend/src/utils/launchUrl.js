const BLOCKED_PROTOCOLS = new Set(['javascript:', 'data:', 'blob:', 'file:'])

/**
 * @param {string} url
 * @param {string[]} [allowedOrigins] Host-configured publisher origins (e.g. https://app.example.com)
 */
export function isAllowedLaunchUrl(url, allowedOrigins = []) {
  if (!url || typeof url !== 'string') return false

  let parsed
  try {
    parsed = new URL(url)
  } catch {
    return false
  }

  if (BLOCKED_PROTOCOLS.has(parsed.protocol)) return false
  if (parsed.protocol !== 'https:') return false

  if (allowedOrigins.length > 0) {
    const origin = parsed.origin
    return allowedOrigins.some((entry) => {
      try {
        return new URL(entry).origin === origin
      } catch {
        return false
      }
    })
  }

  return true
}

export function resolveLaunchUrl(responseData) {
  const data = responseData?.data ?? responseData
  return data?.runtime_url ?? data?.launch?.url ?? ''
}
