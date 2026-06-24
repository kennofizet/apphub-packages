/**
 * Verify an App Hub launch_token from your publisher backend (Node 18+).
 *
 *   APPHUB_BACKEND_URL=http://localhost:8000/api/jmm/zz/apphub node verify.mjs <token> [app_slug]
 */
import { pathToFileURL } from 'node:url'

const APPHUB_BASE = process.env.APPHUB_BACKEND_URL?.replace(/\/$/, '')
const PUBLISHER_ORIGIN = (
  process.env.APPHUB_PUBLISHER_ORIGIN
  || process.env.PUBLISHER_ORIGIN
  || 'http://localhost:51732'
).replace(/\/$/, '')
const BRIDGE_PROXY_SECRET = String(process.env.APPHUB_BRIDGE_PROXY_SECRET || '').trim()

export async function verifyLaunchToken(launchToken, appSlug) {
  if (!APPHUB_BASE) {
    throw new Error('Set APPHUB_BACKEND_URL (Hub API base, no trailing slash)')
  }
  if (!launchToken || launchToken.length < 32) {
    throw new Error('launch_token is required')
  }

  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  }
  if (BRIDGE_PROXY_SECRET !== '') {
    headers['X-AppHub-Bridge-Proxy-Secret'] = BRIDGE_PROXY_SECRET
    headers['X-AppHub-Publisher-Origin'] = PUBLISHER_ORIGIN
  }

  const res = await fetch(`${APPHUB_BASE}/verify-launch-token`, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      launch_token: launchToken,
      ...(appSlug ? { app_slug: appSlug } : {}),
    }),
  })

  const json = await res.json().catch(() => ({}))
  if (!res.ok) {
    const msg = json?.message || json?.error || `verify failed (${res.status})`
    throw new Error(typeof msg === 'string' ? msg : JSON.stringify(msg))
  }

  return json?.data ?? json
}

const isMain = process.argv[1]
  && import.meta.url === pathToFileURL(process.argv[1]).href

if (isMain) {
  const token = process.argv[2]
  const slug = process.argv[3]
  if (!token) {
    console.error('Usage: APPHUB_BACKEND_URL=... node verify.mjs <launch_token> [app_slug]')
    process.exit(1)
  }
  verifyLaunchToken(token, slug)
    .then((data) => console.log(JSON.stringify(data, null, 2)))
    .catch((err) => {
      console.error(err.message)
      process.exit(1)
    })
}
