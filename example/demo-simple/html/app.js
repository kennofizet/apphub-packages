const btn = document.getElementById('btn')
const btnVerify = document.getElementById('btn-verify')
const hello = document.getElementById('hello')
const status = document.getElementById('status')
const versionEl = document.getElementById('version')

const params = new URLSearchParams(window.location.search)
const launchToken = params.get('launch_token')

/** @type {string[]} from manifest.json api_urls */
let manifestApiUrls = []

if (launchToken) {
  status.textContent = 'Launch token present in URL.'
}

function runtimeUrl(path) {
  if (!launchToken) return path
  const sep = path.includes('?') ? '&' : '?'
  return `${path}${sep}launch_token=${encodeURIComponent(launchToken)}`
}

fetch(runtimeUrl('./manifest.json'))
  .then((res) => (res.ok ? res.json() : null))
  .then((manifest) => {
    if (manifest?.version && versionEl) {
      versionEl.textContent = `Version ${manifest.version}`
    }
    manifestApiUrls = normalizeApiUrls(manifest)
    refreshBridgeStatus()
    if (status && manifestApiUrls.length && !bridgeReady) {
      status.textContent += ` Publisher backend: ${manifestApiUrls[0]}`
    }
  })
  .catch(() => {
    if (versionEl) versionEl.hidden = true
  })

const BRIDGE_CHANNEL = 'apphub:bridge'
let bridgeReady = false
/** @type {Record<string, unknown> | null} */
let bridgeContext = null
/** @type {{ id: number, name: string } | null} */
let displayUser = null

function normalizeApiUrls(manifest) {
  if (!manifest || typeof manifest !== 'object') return []
  const urls = []
  if (Array.isArray(manifest.api_urls)) {
    for (const item of manifest.api_urls) {
      if (typeof item === 'string' && item.trim()) urls.push(item.trim().replace(/\/$/, ''))
    }
  }
  if (typeof manifest.api_base_url === 'string' && manifest.api_base_url.trim()) {
    const legacy = manifest.api_base_url.trim().replace(/\/$/, '')
    if (!urls.includes(legacy)) urls.unshift(legacy)
  }
  return urls
}

function publisherBackendBase() {
  const fromContext = bridgeContext?.publisher_api_base
  if (typeof fromContext === 'string' && fromContext.trim()) {
    return fromContext.trim().replace(/\/$/, '')
  }
  if (manifestApiUrls.length) return manifestApiUrls[0]
  return ''
}

function refreshBridgeStatus() {
  if (!bridgeReady || !status) return
  const backend = publisherBackendBase() || '(set api_urls in manifest + run local-bridge-proxy)'
  status.textContent = displayUser
    ? `Bridge ready — Verify → ${backend}/bridge/user (publisher backend, IP-checked by App Hub)`
    : `Bridge ready — Verify uses publisher backend at ${backend}`
}

function callBridge(method, args) {
  return new Promise((resolve, reject) => {
    const id = `demo-${Date.now()}`
    const onMsg = (event) => {
      const msg = event.data
      if (!msg || msg.channel !== BRIDGE_CHANNEL || msg.event !== 'apphub:bridge:result' || msg.id !== id) return
      window.removeEventListener('message', onMsg)
      if (msg.ok) resolve(msg.result)
      else reject(new Error(msg.error || 'Bridge error'))
    }
    window.addEventListener('message', onMsg)
    window.parent.postMessage({ channel: BRIDGE_CHANNEL, event: 'apphub:bridge:call', id, method, args }, '*')
  })
}

/** Call publisher tool backend (local-bridge-proxy) — App Hub checks proxy IP vs api_urls. */
async function fetchBridgeUserViaPublisherBackend() {
  const slug = typeof bridgeContext?.app_slug === 'string' ? bridgeContext.app_slug : ''
  const token = launchToken || (typeof bridgeContext?.launch_token === 'string' ? bridgeContext.launch_token : '')
  const base = publisherBackendBase()
  if (!slug || !token || !base) {
    throw new Error('Need app_slug, launch_token, and manifest api_urls (run example/local-bridge-proxy)')
  }

  const res = await fetch(`${base}/bridge/user`, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      'X-AppHub-Launch-Token': token,
      'X-AppHub-App-Slug': slug,
    },
  })

  const body = await res.json().catch(() => ({}))
  if (!res.ok) {
    throw new Error(body?.error || res.statusText || 'Publisher backend / bridge error')
  }

  return body?.data ?? body
}

window.addEventListener('message', (event) => {
  const msg = event.data
  if (!msg || msg.channel !== BRIDGE_CHANNEL || msg.event !== 'apphub:bridge:ready') return
  bridgeReady = true
  bridgeContext = msg.context ?? null
  const raw = msg.context?.display_user
  if (raw && raw.id != null) {
    displayUser = {
      id: Number(raw.id),
      name: typeof raw.name === 'string' && raw.name.trim() ? raw.name.trim() : String(raw.id),
    }
  }
  const backend = publisherBackendBase() || '(set api_urls in manifest + run local-bridge-proxy)'
  refreshBridgeStatus()
})

btn?.addEventListener('click', () => {
  hello.hidden = false
  if (!bridgeReady) {
    hello.textContent = 'Hello from demo-simple-html!'
    return
  }
  if (displayUser?.name) {
    hello.textContent = `Hello ${displayUser.name}! (display_user — UI only, no API)`
    return
  }
  hello.textContent = 'No display_user from Hub — use Verify for API user.'
})

btnVerify?.addEventListener('click', async () => {
  hello.hidden = false
  if (!bridgeReady) {
    hello.textContent = window.parent === window
      ? 'Open this app from App Hub desktop (not the zip file directly)'
      : 'Bridge not ready — waiting for Hub… try again in a second'
    requestBridgeReady()
    return
  }
  try {
    const granted = await callBridge('requestPermission', ['user.read'])
    if (!granted) {
      hello.textContent = 'user.read denied — accept install permissions or allow in dialog'
      return
    }
    const user = await fetchBridgeUserViaPublisherBackend()
    hello.textContent = `Verified: ${user?.name || 'user'} (id ${user?.id}) via publisher backend ${publisherBackendBase()}/bridge/user`
  } catch (err) {
    const msg = err instanceof Error ? err.message : 'Request failed'
    if (msg === 'Failed to fetch') {
      hello.textContent = `Blocked calling ${publisherBackendBase()}/bridge/user — close app, reopen from Hub (fresh CSP), proxy on :51732`
      return
    }
    hello.textContent = msg.includes('App Hub API not found') || msg.includes('Invalid or expired')
      ? msg
      : `${msg} — check proxy APPHUB_BACKEND_URL (hub-host .env, often :8000)`
  }
})

function requestBridgeReady() {
  if (window.parent === window) return
  window.parent.postMessage({ channel: BRIDGE_CHANNEL, event: 'apphub:bridge:ping' }, '*')
}

if (window.parent !== window) {
  if (status && !launchToken) {
    status.textContent = 'Waiting for Hub bridge…'
  }
  requestBridgeReady()
}
