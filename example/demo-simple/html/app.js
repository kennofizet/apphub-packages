const btn = document.getElementById('btn')
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
  if (status) status.textContent = 'Bridge ready — click Hello to try user.read'
})

btn?.addEventListener('click', async () => {
  hello.hidden = false
  if (!bridgeReady) {
    hello.textContent = 'Hello from demo-simple-html!'
    return
  }
  try {
    // const ok = await callBridge('requestPermission', ['user.read'])
    // if (!ok) {
    //   hello.textContent = 'Permission denied'
    //   return
    // }
    const user = await callBridge('getUserInfo', [])
    hello.textContent = `Hello ${user?.name || 'user'}!`
  } catch {
    hello.textContent = 'Bridge call failed'
  }
})
