const btn = document.getElementById('btn')
const btnVerify = document.getElementById('btn-verify')
const btnNotify = document.getElementById('btn-notify')
const btnReportError = document.getElementById('btn-report-error')
const btnCallParent = document.getElementById('btn-call-parent')
const btnCallParentReal = document.getElementById('btn-call-parent-real')
const hello = document.getElementById('hello')
const status = document.getElementById('status')
const versionEl = document.getElementById('version')

const params = new URLSearchParams(window.location.search)

if (typeof AppHubPublisherBridge === 'undefined') {
  const status = document.getElementById('status')
  if (status) {
    status.textContent = 'Missing publisher-bridge.js — run: cd example && npm run sync:shared, then repack zip'
  }
  throw new Error('publisher-bridge.js not loaded')
}

const bridge = AppHubPublisherBridge.createPublisherBridge({
  getUrlLaunchToken: () => new URLSearchParams(window.location.search).get('launch_token'),
})

const launchToken = params.get('launch_token')
if (launchToken && status) {
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
    bridge.setManifestApiUrls(bridge.normalizeApiUrls(manifest))
    if (status && bridge.publisherBackendBase() && !bridge.ready) {
      status.textContent += ` Publisher backend: ${bridge.publisherBackendBase()}`
    }
    refreshStatus()
  })
  .catch(() => {
    if (versionEl) versionEl.hidden = true
  })

function refreshStatus() {
  if (!bridge.ready || !status) return
  status.textContent = bridge.statusLine()
}

window.addEventListener('message', (event) => {
  if (!bridge.handleBridgeMessage(event.data)) return
  refreshStatus()
})

btn?.addEventListener('click', () => {
  hello.hidden = false
  if (!bridge.ready) {
    hello.textContent = 'Hello from demo-simple-html!'
    return
  }
  const display = bridge.displayUser()
  if (display?.name) {
    hello.textContent = `Hello ${display.name}! (display_user — UI only, no API)`
    return
  }
  hello.textContent = 'No display_user from Hub — use Verify for API user.'
})

btnVerify?.addEventListener('click', async () => {
  hello.hidden = false
  if (!bridge.ready) {
    hello.textContent = window.parent === window
      ? 'Open this app from App Hub desktop (not the zip file directly)'
      : 'Bridge not ready — waiting for Hub… try again in a second'
    bridge.requestBridgeReady()
    return
  }
  try {
    const user = await bridge.fetchBridgeUser()
    hello.textContent = `Verified: ${user?.name || 'user'} (id ${user?.id}) via ${bridge.publisherBackendBase()}/bridge/user`
  } catch (err) {
    hello.textContent = bridge.formatHttpError(err, 'user')
  }
})

btnNotify?.addEventListener('click', async () => {
  hello.hidden = false
  if (!bridge.ready) {
    hello.textContent = 'Bridge not ready — open from App Hub, then try again'
    bridge.requestBridgeReady()
    return
  }
  if (!bridge.scopesGranted().includes('desktop.notify')) {
    hello.textContent = 'desktop.notify missing on launch token — reinstall app with notify permission, then reopen'
    return
  }
  if (!bridge.publisherBackendBase()) {
    hello.textContent = 'No api_urls — add to manifest and run local-bridge-proxy on :51732'
    return
  }
  btnNotify.disabled = true
  hello.textContent = `Sending via ${bridge.publisherBackendBase()}/bridge/notify…`
  try {
    const result = await bridge.fetchBridgeNotify({
      title: 'Demo Simple',
      body: 'desktop.notify via publisher backend (shared publisher-bridge.js)',
      broadcast: true,
    })
    const n = result?.recipients
    const extra = typeof n === 'number' ? ` (${n} recipient${n === 1 ? '' : 's'})` : ''
    hello.textContent = `Notify sent${extra} — check Hub bell + toasts`
  } catch (err) {
    hello.textContent = bridge.formatHttpError(err, 'notify')
  } finally {
    btnNotify.disabled = false
  }
})

btnReportError?.addEventListener('click', async () => {
  hello.hidden = false
  if (!bridge.ready) {
    hello.textContent = 'Bridge not ready — open from App Hub, then try again'
    bridge.requestBridgeReady()
    return
  }
  const err = new Error('demo-simple-html smoke test error')
  err.name = 'DemoSmokeError'
  try {
    await bridge.callBridge('reportError', {
      message: err.message,
      name: err.name,
      stack: err.stack || '',
    })
    hello.textContent = 'reportError sent — check apphub_app_usage_logs (action=error) for this app slug'
  } catch (bridgeErr) {
    hello.textContent = bridgeErr instanceof Error ? bridgeErr.message : 'reportError failed'
  }
})

async function runCallParent(btn, { forceReal = false } = {}) {
  hello.hidden = false
  if (!bridge.ready) {
    hello.textContent = 'Bridge not ready — open from App Hub (product iframe shell), then try again'
    bridge.requestBridgeReady()
    return
  }
  const hasScope = bridge.scopesGranted().includes('parent.project.list')
  const mode = forceReal ? 'real API' : 'auto'
  btn.disabled = true
  hello.textContent = hasScope
    ? `callParent(project.list) [${mode}]…`
    : `callParent(project.list) [${mode}]… (no scope)`
  try {
    const result = await bridge.callParent(
      'project.list',
      { query: { page: 1 } },
      forceReal ? { forceReal: true } : undefined,
    )
    hello.textContent = `callParent ok [${mode}] — result: ${JSON.stringify(result)}`
  } catch (err) {
    hello.textContent = `[${mode}] ${err instanceof Error ? err.message : 'callParent failed'}`
  } finally {
    btn.disabled = false
  }
}

btnCallParent?.addEventListener('click', () => runCallParent(btnCallParent, { forceReal: false }))
btnCallParentReal?.addEventListener('click', () => runCallParent(btnCallParentReal, { forceReal: true }))

if (window.parent !== window) {
  if (status && !launchToken) {
    status.textContent = 'Waiting for Hub bridge…'
  }
  bridge.requestBridgeReady()
}
