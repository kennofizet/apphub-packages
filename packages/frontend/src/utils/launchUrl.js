import { resolveRuntimeApiBase } from './originSafety.js'

const BLOCKED_PROTOCOLS = new Set(['javascript:', 'data:', 'blob:', 'file:'])

export const RUNTIME_HOSTED = 'hosted'
export const RUNTIME_IFRAME = 'iframe'

/** Hub-served bundle under {runtimeBase}/apps/{slug}/runtime/ */
export function isHubHostedRuntimeUrl(url, runtimeBaseOrOptions) {
  const base = typeof runtimeBaseOrOptions === 'object'
    ? resolveRuntimeApiBase(runtimeBaseOrOptions)
    : String(runtimeBaseOrOptions ?? '').replace(/\/$/, '')

  if (!base || !url || typeof url !== 'string') return false
  try {
    const parsed = new URL(url)
    const baseParsed = new URL(base)
    if (parsed.origin !== baseParsed.origin) return false
    return /\/apps\/[^/]+\/runtime\//.test(parsed.pathname)
  } catch {
    return false
  }
}

/**
 * @param {string} url
 * @param {string[]} [allowedOrigins]
 * @param {{ backendUrl?: string, runtimePublicUrl?: string, runtimeType?: string }} [options]
 */
export function isAllowedLaunchUrl(url, allowedOrigins = [], options = {}) {
  if (!url || typeof url !== 'string') return false

  if (options.runtimeType === RUNTIME_HOSTED || isHubHostedRuntimeUrl(url, options)) {
    return isHubHostedRuntimeUrl(url, options)
  }

  let parsed
  try {
    parsed = new URL(url)
  } catch {
    return false
  }

  if (BLOCKED_PROTOCOLS.has(parsed.protocol)) return false

  if (parsed.protocol === 'http:' && (parsed.hostname === 'localhost' || parsed.hostname === '127.0.0.1')) {
    return true
  }

  if (parsed.protocol !== 'https:') return false

  if (allowedOrigins.length === 0) {
    return false
  }

  const origin = parsed.origin
  return allowedOrigins.some((entry) => {
    try {
      return new URL(entry).origin === origin
    } catch {
      return false
    }
  })
}

/** @param {string} entryUrl */
export function entryUrlOrigin(entryUrl) {
  if (!entryUrl || typeof entryUrl !== 'string') return ''
  try {
    return new URL(entryUrl).origin
  } catch {
    return ''
  }
}

/** Check catalog entry_url origin against host allowedRuntimeOrigins. */
export function isEntryUrlAllowed(entryUrl, allowedOrigins = []) {
  if (!entryUrl || typeof entryUrl !== 'string') return false
  return isAllowedLaunchUrl(entryUrl, allowedOrigins)
}

export function resolveLaunchUrl(responseData) {
  const data = responseData?.data ?? responseData
  const base = data?.runtime_url ?? data?.entry_url ?? data?.launch?.url ?? ''
  const token = data?.launch_token
  if (!base) return ''
  if (!token || typeof token !== 'string') return base

  try {
    const url = new URL(base)
    url.searchParams.set('launch_token', token)
    return url.toString()
  } catch {
    return base
  }
}

/**
 * Strict sandbox for all Hub runtimes (no allow-same-origin).
 * Publisher iframe gets opaque/null origin — cannot read Hub localStorage (different parent origin).
 * Publisher also cannot use normal first-party storage on entry_url; use Hub bridge + publisher backend.
 * @param {string} runtimeType
 */
export function iframeSandboxAttrs(runtimeType) {
  void runtimeType
  return 'allow-scripts allow-forms allow-popups'
}

/** True when iframe document has opaque origin (hosted + strict iframe). */
export function iframeUsesOpaqueSandbox(runtimeType) {
  return runtimeType === RUNTIME_HOSTED || runtimeType === RUNTIME_IFRAME
}
