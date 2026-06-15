import { readUrlParam } from './apphub-config.js'

export function isEmbeddedHubFrame() {
  return typeof window !== 'undefined' && window.self !== window.top
}

/** Hub host opened directly in the browser tab (not inside product iframe). */
export function isStandaloneTopLevelHub() {
  if (typeof window === 'undefined') return false
  return window.self === window.top
}

/**
 * Dev-only: fetch or read a session when opening the hub host URL directly.
 * Enabled in Vite dev (`npm run dev`) or when VITE_APPHUB_STANDALONE_DEV=true.
 */
export function isStandaloneDevSessionEnabled() {
  if (!isStandaloneTopLevelHub()) return false
  if (import.meta.env.DEV) return true
  const flag = String(import.meta.env.VITE_APPHUB_STANDALONE_DEV ?? '').trim().toLowerCase()
  return flag === '1' || flag === 'true'
}

/**
 * Token sources (first match wins): ?token= URL, VITE_APPHUB_DEV_TOKEN, VITE_APPHUB_DEV_LOGIN_URL fetch.
 * @returns {Promise<string>}
 */
export async function resolveStandaloneDevToken() {
  const fromUrl = readUrlParam('token').trim()
  if (fromUrl) return fromUrl

  const fromEnv = String(import.meta.env.VITE_APPHUB_DEV_TOKEN ?? '').trim()
  if (fromEnv) return fromEnv

  const loginUrl = String(import.meta.env.VITE_APPHUB_DEV_LOGIN_URL ?? '').trim()
  if (!loginUrl) return ''

  const res = await fetch(loginUrl, { credentials: 'include', headers: { Accept: 'application/json' } })
  if (!res.ok) return ''

  const data = await res.json()
  if (data?.success === false) return ''

  return String(data?.rewardplay_token ?? data?.token ?? '').trim()
}
