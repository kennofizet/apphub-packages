const btn = document.getElementById('btn')
const btnVerify = document.getElementById('btn-verify')
const hello = document.getElementById('hello')
const status = document.getElementById('status')
const versionEl = document.getElementById('version')

const params = new URLSearchParams(window.location.search)
const launchToken = params.get('launch_token')

if (launchToken) {
  status.textContent = 'Launch token present in URL.'
}

/** Hub runtime requires launch_token on every bundle file request. */
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
  })
  .catch(() => {
    if (versionEl) versionEl.hidden = true
  })

/** Minimal bridge client (see docs/sdk-stub.js for full copy-paste version). */
const BRIDGE_CHANNEL = 'apphub:bridge'
let bridgeReady = false
/** @type {{ id: number, name: string } | null} UI-only snapshot from Hub bootstrap — not for auth */
let displayUser = null

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

window.addEventListener('message', (event) => {
  const msg = event.data
  if (!msg || msg.channel !== BRIDGE_CHANNEL || msg.event !== 'apphub:bridge:ready') return
  bridgeReady = true
  const raw = msg.context?.display_user
  if (raw && raw.id != null) {
    displayUser = {
      id: Number(raw.id),
      name: typeof raw.name === 'string' && raw.name.trim() ? raw.name.trim() : String(raw.id),
    }
  }
  if (status) {
    status.textContent = displayUser
      ? `Bridge ready — UI greeting uses display_user (${displayUser.name}); API verify is optional.`
      : 'Bridge ready — click Hello (display_user) or Verify (getUserInfo API).'
  }
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
    hello.textContent = 'Bridge not ready'
    return
  }
  try {
    const user = await callBridge('getUserInfo', [])
    hello.textContent = `Verified: ${user?.name || 'user'} (id ${user?.id}) via GET bridge/user`
  } catch {
    hello.textContent = 'getUserInfo failed — grant user.read or accept install permissions'
  }
})
