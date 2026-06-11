/**
 * Build-time config (CI/CD). Session + UI prefs come from parent postMessage only.
 */

export function readUrlParam(name) {
  if (typeof window === 'undefined') return ''
  return new URLSearchParams(window.location.search).get(name) || ''
}

/** API URLs from VITE_* — safe to be public in the built bundle. */
export function resolveAppHubStaticConfig() {
  const backendUrl = String(import.meta.env.VITE_APPHUB_BACKEND_URL ?? '').replace(/\/$/, '')
  const coreUrl = String(import.meta.env.VITE_APPHUB_CORE_URL ?? '').replace(/\/$/, '')
  const hostAccessSecret = String(import.meta.env.VITE_APPHUB_HOST_ACCESS_SECRET ?? '').trim()

  return {
    backendUrl,
    coreUrl,
    hostAccessSecret,
  }
}

export function resolveInitialOpenSlug() {
  return readUrlParam('open').trim()
}
