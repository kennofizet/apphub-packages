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

function parseLaunchUrl(url) {
  if (!url || typeof url !== 'string') return null
  try {
    return new URL(url)
  } catch {
    return null
  }
}

function isLocalhostHttp(parsed) {
  return parsed.protocol === 'http:'
    && (parsed.hostname === 'localhost' || parsed.hostname === '127.0.0.1')
}

/**
 * HTTPS, localhost HTTP (local dev), or plain HTTP when origin is on the enterprise allowlist.
 * @param {string} url
 * @param {string[]} [allowedOrigins] host APPHUB_ALLOWED_RUNTIME_ORIGINS / installAppHubModule.allowedRuntimeOrigins
 */
export function isEntryUrlFormatAllowed(url, allowedOrigins = []) {
  const parsed = parseLaunchUrl(url)
  if (!parsed) return false
  if (BLOCKED_PROTOCOLS.has(parsed.protocol)) return false
  if (isLocalhostHttp(parsed)) return true
  if (parsed.protocol === 'https:') return true
  if (parsed.protocol === 'http:' && Array.isArray(allowedOrigins) && allowedOrigins.length > 0) {
    return allowedOrigins.some((entry) => {
      try {
        return new URL(entry).origin === parsed.origin
      } catch {
        return false
      }
    })
  }
  return false
}

/**
 * Optional host enterprise cap (installAppHubModule allowedRuntimeOrigins).
 * Empty = trust catalog entry_url after DEV approval.
 */
export function passesEnterpriseRuntimeOrigins(origin, allowedOrigins = []) {
  if (!origin || !Array.isArray(allowedOrigins) || allowedOrigins.length === 0) {
    return true
  }
  return allowedOrigins.some((entry) => {
    try {
      return new URL(entry).origin === origin
    } catch {
      return false
    }
  })
}

/**
 * Draft preflight + catalog check: valid URL format + optional enterprise cap.
 * Per-app allowlist is apps.entry_url in the database (DEV approves).
 */
export function isCatalogEntryUrlAllowed(entryUrl, allowedOrigins = []) {
  if (!isEntryUrlFormatAllowed(entryUrl, allowedOrigins)) return false
  const origin = entryUrlOrigin(entryUrl)
  return passesEnterpriseRuntimeOrigins(origin, allowedOrigins)
}

/**
 * @param {string} url
 * @param {string[]} [allowedOrigins] optional enterprise host policy
 * @param {{
 *   backendUrl?: string,
 *   runtimePublicUrl?: string,
 *   runtimeType?: string,
 *   catalogEntryUrl?: string|null,
 * }} [options]
 */
export function isAllowedLaunchUrl(url, allowedOrigins = [], options = {}) {
  if (!url || typeof url !== 'string') return false

  if (options.runtimeType === RUNTIME_HOSTED || isHubHostedRuntimeUrl(url, options)) {
    return isHubHostedRuntimeUrl(url, options)
  }

  const parsed = parseLaunchUrl(url)
  if (!parsed) return false
  if (!isEntryUrlFormatAllowed(url, allowedOrigins)) return false

  const catalogOrigin = entryUrlOrigin(options.catalogEntryUrl ?? '')
  if (catalogOrigin && parsed.origin !== catalogOrigin) {
    return false
  }

  return passesEnterpriseRuntimeOrigins(parsed.origin, allowedOrigins)
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

/** @deprecated use isCatalogEntryUrlAllowed */
export function isEntryUrlAllowed(entryUrl, allowedOrigins = []) {
  return isCatalogEntryUrlAllowed(entryUrl, allowedOrigins)
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
 * Hosted: opaque origin (no allow-same-origin) — untrusted zip on Hub infra.
 * Iframe (approved entry_url): allow-same-origin so publisher SPAs load assets/storage on their host.
 * Does not grant Hub localStorage — parent Hub is a different origin.
 * @param {string} runtimeType
 */
/**
 * @param {string} runtimeType
 * @param {string[]} [permissions]
 */
export function iframeSandboxAttrs(runtimeType, permissions = []) {
  const perms = Array.isArray(permissions) ? permissions : []
  const downloads = perms.includes('desktop.download') ? ' allow-downloads' : ''
  if (runtimeType === RUNTIME_HOSTED) {
    return `allow-scripts allow-forms allow-popups${downloads}`
  }
  if (runtimeType === RUNTIME_IFRAME) {
    return `allow-scripts allow-forms allow-popups allow-same-origin${downloads}`
  }
  return `allow-scripts allow-forms allow-popups${downloads}`
}

/** Opaque sandbox only for Hub-hosted bundles. */
export function iframeUsesOpaqueSandbox(runtimeType) {
  return runtimeType === RUNTIME_HOSTED
}
