/**
 * Hosted runtime is loaded in a nested iframe (product → hub → app).
 * CSP frame-ancestors must allow every ancestor origin — pass them on the launch URL.
 */

function appendQueryParam(url, key, value) {
  if (!url || !value) return url
  try {
    const parsed = new URL(url)
    if (parsed.searchParams.has(key)) return url
    parsed.searchParams.set(key, value)
    return parsed.toString()
  } catch {
    return url
  }
}

/**
 * @param {string} launchUrl
 * @param {{ hubOrigin?: string, productOrigin?: string }} [options]
 */
export function appendHostedFrameAncestorParams(launchUrl, options = {}) {
  let url = launchUrl
  const hubOrigin = String(options.hubOrigin ?? '').trim()
  const productOrigin = String(options.productOrigin ?? '').trim()

  if (hubOrigin) {
    url = appendQueryParam(url, 'hub_origin', hubOrigin)
  }
  if (productOrigin && productOrigin !== hubOrigin) {
    url = appendQueryParam(url, 'product_origin', productOrigin)
  }

  return url
}
