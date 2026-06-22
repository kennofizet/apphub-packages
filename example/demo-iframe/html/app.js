const btn = document.getElementById('btn')
const btnVerify = document.getElementById('btn-verify')
const btnReportError = document.getElementById('btn-report-error')
const hello = document.getElementById('hello')
const status = document.getElementById('status')
const versionEl = document.getElementById('version')

const params = new URLSearchParams(window.location.search)
const launchToken = params.get('launch_token')

/** @type {string[]} from Hub bridge (manifest api_urls — iframe uses publisher origin; api_urls from bridge:ready) */
let manifestApiUrls = []

if (launchToken) {
  status.textContent = 'Launch token present in URL.'
}

const BRIDGE_CHANNEL = 'apphub:bridge'
let bridgeReady = false
/** @type {Record<string, unknown> | null} */
let bridgeContext = null
/** @type {{ id: number, name: string } | null} */
let displayUser = null

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
  const backend = publisherBackendBase() || '(Hub bridge publisher_api_base + local-bridge-proxy)'
  status.textContent = displayUser
    ? `Bridge ready — Verify → ${backend}/bridge/user`
    : `Bridge ready — Verify uses ${backend}`
}

async function fetchBridgeUserViaPublisherBackend() {
  const slug = typeof bridgeContext?.app_slug === 'string' ? bridgeContext.app_slug : ''
  const token = launchToken || (typeof bridgeContext?.launch_token === 'string' ? bridgeContext.launch_token : '')
  const base = publisherBackendBase()
  if (!slug || !token || !base) {
    throw new Error('Need app_slug, launch_token, and publisher_api_base from Hub bridge (run local-bridge-proxy)')
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

function callBridge(method, args) {
  const bridgeArgs = Array.isArray(args) ? args : [args]
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
    window.parent.postMessage({ channel: BRIDGE_CHANNEL, event: 'apphub:bridge:call', id, method, args: bridgeArgs }, '*')
  })
}

window.addEventListener('message', (event) => {
  const msg = event.data
  if (!msg || msg.channel !== BRIDGE_CHANNEL || msg.event !== 'apphub:bridge:ready') return
  bridgeReady = true
  bridgeContext = msg.context ?? null

  const version = bridgeContext?.app_version
  if (version && versionEl) {
    versionEl.textContent = `Version ${version}`
  } else if (versionEl) {
    versionEl.hidden = true
  }

  const pubBase = bridgeContext?.publisher_api_base
  if (typeof pubBase === 'string' && pubBase.trim()) {
    manifestApiUrls = [pubBase.trim().replace(/\/$/, '')]
  }

  const raw = bridgeContext?.display_user
  if (raw && raw.id != null) {
    displayUser = {
      id: Number(raw.id),
      name: typeof raw.name === 'string' && raw.name.trim() ? raw.name.trim() : String(raw.id),
    }
  }
  refreshBridgeStatus()
})

btn?.addEventListener('click', () => {
  hello.hidden = false
  if (!bridgeReady) {
    hello.textContent = 'Hello from demo-iframe-html!'
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
      ? 'Open this app from App Hub desktop (serve html/ on :15180 first)'
      : 'Bridge not ready — waiting for Hub… try again in a second'
    requestBridgeReady()
    return
  }
  try {
    const scopes = Array.isArray(bridgeContext?.scopes_granted) ? bridgeContext.scopes_granted : []
    if (!scopes.includes('user.read') && !scopes.includes('user.profile')) {
      hello.textContent = 'user.read not on launch token — accept install permissions, then reopen app from Hub'
      return
    }
    const user = await fetchBridgeUserViaPublisherBackend()
    hello.textContent = `Verified: ${user?.name || 'user'} (id ${user?.id}) via ${publisherBackendBase()}/bridge/user`
  } catch (err) {
    const msg = err instanceof Error ? err.message : 'Request failed'
    if (msg === 'Failed to fetch') {
      hello.textContent = `Blocked calling ${publisherBackendBase()}/bridge/user — run local-bridge-proxy on :51732`
      return
    }
    hello.textContent = msg
  }
})

btnReportError?.addEventListener('click', async () => {
  hello.hidden = false
  if (!bridgeReady) {
    hello.textContent = 'Bridge not ready — open from App Hub, then try again'
    requestBridgeReady()
    return
  }
  const err = new Error('demo-iframe-html smoke test error')
  err.name = 'DemoSmokeError'
  try {
    await callBridge('reportError', {
      message: err.message,
      name: err.name,
      stack: err.stack || '',
    })
    hello.textContent = 'reportError sent — check apphub_app_usage_logs (action=error) for this app slug'
  } catch (bridgeErr) {
    hello.textContent = bridgeErr instanceof Error ? bridgeErr.message : 'reportError failed'
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
