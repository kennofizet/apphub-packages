import axios from 'axios'

const REQUEST_TIMEOUT_MS = 30_000

/**
 * packages-core client (zones, auth) — same pattern as workpoint/rewardplay.
 */
export function createCoreApi(coreUrl, token) {
  const baseURL = (coreUrl || '').replace(/\/$/, '')

  const client = axios.create({
    baseURL,
    timeout: REQUEST_TIMEOUT_MS,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { 'X-Knf-Token': token } : {}),
    },
  })

  return {
    authCheck: () => client.get('/auth/check'),
    getPlayerZones: () => client.get('/player/zones'),
  }
}
