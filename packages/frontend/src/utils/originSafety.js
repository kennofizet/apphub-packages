export const ORIGIN_UNSAFE_SAME_ORIGIN_EMBED = 'same_origin_embed'
export const ORIGIN_UNSAFE_NOT_CONFIGURED = 'hub_origin_not_configured'
export const ORIGIN_UNSAFE_WRONG_ORIGIN = 'hub_origin_mismatch'
export const ORIGIN_UNSAFE_RUNTIME_NOT_CONFIGURED = 'runtime_public_url_not_configured'
export const ORIGIN_UNSAFE_RUNTIME_SAME_ORIGIN = 'runtime_same_origin_as_hub'

function parseOrigin(value) {
  const trimmed = String(value ?? '').trim()
  if (!trimmed) return null
  try {
    return new URL(trimmed).origin
  } catch {
    return null
  }
}

/** localhost / 127.0.0.1 — same gate as evaluateOriginSafety devFriendly bypass. */
export function isLocalDevOrigin(origin) {
  try {
    const host = new URL(origin).hostname
    return host === 'localhost' || host === '127.0.0.1'
  } catch {
    return false
  }
}

/** True on page when devFriendly origin rules may apply (matches originSafety.js). */
export function isLocalDevHostPage() {
  if (typeof window === 'undefined') return false
  return isLocalDevOrigin(window.location.origin)
}

export function parseDevUserFromBootstrap(resp) {
  const data = resp?.data?.data ?? resp?.data ?? {}
  return data.is_dev_user === true
}

/**
 * @param {import('axios').AxiosResponse|{ data?: unknown }} resp
 */
export function parseOriginsFromBootstrap(resp) {
  const data = resp?.data?.data ?? resp?.data ?? {}
  const origins = data.origins ?? {}
  return {
    hubPublicUrl: String(origins.hub_public_url ?? '').trim(),
    frontendOrigin: String(origins.frontend_origin ?? '').trim(),
    runtimePublicUrl: String(origins.runtime_public_url ?? '').trim(),
    originsAuto: origins.auto_derived === true,
  }
}

/**
 * @param {{ runtimePublicUrl?: string, backendUrl?: string }} options
 */
export function resolveRuntimeApiBase(options = {}) {
  const configured = String(options.runtimePublicUrl ?? '').trim().replace(/\/$/, '')
  if (configured) return configured
  return String(options.backendUrl ?? '').trim().replace(/\/$/, '')
}

/**
 * Merge client install options with server bootstrap origins.
 *
 * @param {{
 *   hubOrigin?: string,
 *   runtimePublicUrl?: string,
 *   backendUrl?: string,
 *   serverHubPublicUrl?: string,
 *   serverFrontendOrigin?: string,
 *   serverRuntimePublicUrl?: string,
 *   serverOriginsAuto?: boolean,
 *   enforceDevFriendlyOrigins?: boolean,
 *   isDevUser?: boolean,
 * }} [options]
 */
export function resolveEffectiveOrigins(options = {}) {
  const currentOrigin = typeof window !== 'undefined' ? window.location.origin : ''
  const localDev = isLocalDevOrigin(currentOrigin)
  const isDevUser = options.isDevUser === true
  const devFriendlyToggleOn = options.enforceDevFriendlyOrigins !== false

  const clientHub = String(options.hubOrigin ?? '').trim()
  const clientRuntime = String(options.runtimePublicUrl ?? '').trim()
  const serverHub = String(options.serverHubPublicUrl ?? '').trim()
  const serverFrontend = String(options.serverFrontendOrigin ?? '').trim()
  const serverRuntime = String(options.serverRuntimePublicUrl ?? '').trim()
  const originsAuto = options.serverOriginsAuto === true

  let hubOrigin = clientHub || serverHub
  let runtimePublicUrl = clientRuntime || serverRuntime

  let devFriendly = false

  // Relaxed localhost bypass — dev users only (toggle in DEV bar).
  if (localDev && isDevUser && devFriendlyToggleOn) {
    if (!clientHub) hubOrigin = currentOrigin
    if (!clientRuntime && options.backendUrl) {
      runtimePublicUrl = resolveRuntimeApiBase({ backendUrl: options.backendUrl })
    }
    devFriendly = true
  } else if (localDev && originsAuto && (serverFrontend || serverHub)) {
    // Strict localhost — non-dev, or dev with strict toggle: Hub SPA origin from bootstrap Origin header.
    hubOrigin = clientHub || serverFrontend || serverHub
  } else if (!localDev && (serverFrontend || serverHub)) {
    hubOrigin = clientHub || serverFrontend || serverHub
  }

  return {
    hubOrigin,
    runtimePublicUrl,
    devFriendly,
    localDev,
    originsAuto,
    currentOrigin,
  }
}

function safeResult(extra = {}) {
  return {
    safe: true,
    pending: false,
    loading: false,
    reason: null,
    parentOrigin: null,
    expectedHubOrigin: null,
    expectedRuntimeOrigin: null,
    devFriendly: false,
    ...extra,
  }
}

function unsafe(reason, extra = {}) {
  return {
    safe: false,
    pending: false,
    loading: false,
    reason,
    parentOrigin: extra.parentOrigin ?? null,
    expectedHubOrigin: extra.expectedHubOrigin ?? null,
    expectedRuntimeOrigin: extra.expectedRuntimeOrigin ?? null,
    devFriendly: false,
  }
}

function loadingResult(extra = {}) {
  return {
    safe: false,
    pending: true,
    loading: true,
    reason: null,
    parentOrigin: null,
    expectedHubOrigin: null,
    expectedRuntimeOrigin: null,
    devFriendly: false,
    ...extra,
  }
}

function checkSameOriginEmbed(expectedHubOrigin, expectedRuntimeOrigin) {
  if (typeof window === 'undefined' || window.self === window.top) {
    return null
  }

  try {
    const parentOrigin = window.parent.location.origin
    if (parentOrigin === window.location.origin) {
      return unsafe(ORIGIN_UNSAFE_SAME_ORIGIN_EMBED, {
        parentOrigin,
        expectedHubOrigin,
        expectedRuntimeOrigin,
      })
    }
  } catch {
    // Cross-origin parent — isolation OK.
  }

  return null
}

/**
 * App Hub must run on a dedicated origin; hosted bundles on another public runtime origin.
 * Local dev relaxed mode — dev users only (APPHUB_DEV_USER_IDS). Non-dev gets strict checks.
 *
 * @param {{
 *   allowSameOriginEmbed?: boolean,
 *   allowUnsafeOrigin?: boolean,
 *   allowSameOriginHostedRuntime?: boolean,
 *   hubOrigin?: string,
 *   runtimePublicUrl?: string,
 *   backendUrl?: string,
 *   serverHubPublicUrl?: string,
 *   serverRuntimePublicUrl?: string,
 *   serverOriginsResolved?: boolean,
 *   hasToken?: boolean,
 *   enforceDedicatedHubOrigin?: boolean,
 *   enforceIsolatedHostedRuntime?: boolean,
 *   enforceDevFriendlyOrigins?: boolean,
 *   isDevUser?: boolean,
 * }} [options]
 */
export function evaluateOriginSafety(options = {}) {
  if (
    options.allowUnsafeOrigin === true
    || options.allowSameOriginEmbed === true
    || options.allowSameOriginHostedRuntime === true
  ) {
    return safeResult()
  }

  if (typeof window === 'undefined') {
    return safeResult()
  }

  const effective = resolveEffectiveOrigins(options)
  const expectedHubOrigin = parseOrigin(effective.hubOrigin)
  const expectedRuntimeOrigin = parseOrigin(effective.runtimePublicUrl)
  const enforceDedicated = options.enforceDedicatedHubOrigin !== false
  const enforceRuntime = options.enforceIsolatedHostedRuntime !== false
  const canBootstrap = options.hasToken === true && Boolean(options.backendUrl)
  const serverResolved = options.serverOriginsResolved === true

  if (effective.devFriendly) {
    const embedBlock = checkSameOriginEmbed(expectedHubOrigin, expectedRuntimeOrigin)
    if (embedBlock) return embedBlock
    return safeResult({
      devFriendly: true,
      expectedHubOrigin,
      expectedRuntimeOrigin,
    })
  }

  const embedBlock = checkSameOriginEmbed(expectedHubOrigin, expectedRuntimeOrigin)
  if (embedBlock) return embedBlock

  if (enforceDedicated) {
    if (!expectedHubOrigin) {
      if (!serverResolved && canBootstrap) {
        return loadingResult({ expectedRuntimeOrigin })
      }
      return unsafe(ORIGIN_UNSAFE_NOT_CONFIGURED, { expectedHubOrigin: null, expectedRuntimeOrigin })
    }

    if (expectedHubOrigin !== effective.currentOrigin) {
      return unsafe(ORIGIN_UNSAFE_WRONG_ORIGIN, { expectedHubOrigin, expectedRuntimeOrigin })
    }
  }

  if (enforceRuntime && !effective.originsAuto && !effective.localDev) {
    if (!expectedRuntimeOrigin) {
      if (!serverResolved && canBootstrap) {
        return loadingResult({ expectedHubOrigin, expectedRuntimeOrigin: null })
      }
      return unsafe(ORIGIN_UNSAFE_RUNTIME_NOT_CONFIGURED, { expectedHubOrigin, expectedRuntimeOrigin: null })
    }

    if (expectedRuntimeOrigin === effective.currentOrigin) {
      return unsafe(ORIGIN_UNSAFE_RUNTIME_SAME_ORIGIN, { expectedHubOrigin, expectedRuntimeOrigin })
    }
  }

  return safeResult({ expectedHubOrigin, expectedRuntimeOrigin })
}

export function isEmbeddedFrame() {
  if (typeof window === 'undefined') return false
  return window.self !== window.top
}
