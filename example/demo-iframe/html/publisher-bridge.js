/**
 * Publisher bridge helpers for App Hub demo apps.
 *
 * Hub postMessage: reportError, sendDesktopMessage, setTaskbarBadge, requestPermission
 * Publisher HTTP (manifest api_urls → local-bridge-proxy): GET bridge/user, POST bridge/notify
 */
;(function initAppHubPublisherBridge(global) {
  const BRIDGE_CHANNEL = 'apphub:bridge'
  const EVENT_READY = 'apphub:bridge:ready'
  const EVENT_CALL = 'apphub:bridge:call'
  const EVENT_RESULT = 'apphub:bridge:result'
  const EVENT_PING = 'apphub:bridge:ping'
  const EVENT_NOTIFY_SENT = 'apphub:publisher:notify-sent'

  function normalizeApiUrls(manifest) {
    if (!manifest || typeof manifest !== 'object') return []
    const urls = []
    if (Array.isArray(manifest.api_urls)) {
      for (const item of manifest.api_urls) {
        if (typeof item === 'string' && item.trim()) {
          urls.push(item.trim().replace(/\/$/, ''))
        }
      }
    }
    if (typeof manifest.api_base_url === 'string' && manifest.api_base_url.trim()) {
      const legacy = manifest.api_base_url.trim().replace(/\/$/, '')
      if (!urls.includes(legacy)) urls.unshift(legacy)
    }
    return urls
  }

  function readJson(res) {
    return res.json().catch(() => ({}))
  }

  function bridgeHeaders(token, slug) {
    return {
      Accept: 'application/json',
      'X-AppHub-Launch-Token': token,
      'X-AppHub-App-Slug': slug,
    }
  }

  /**
   * @param {{
   *   getUrlLaunchToken?: () => string | null,
   *   manifestApiUrls?: string[],
   * }} [options]
   */
  function createPublisherBridge(options = {}) {
    const getUrlLaunchToken = typeof options.getUrlLaunchToken === 'function'
      ? options.getUrlLaunchToken
      : () => null

    let manifestApiUrls = Array.isArray(options.manifestApiUrls) ? [...options.manifestApiUrls] : []
    let bridgeReady = false
    /** @type {Record<string, unknown> | null} */
    let bridgeContext = null

    function setManifestApiUrls(urls) {
      manifestApiUrls = Array.isArray(urls) ? [...urls] : []
    }

    function launchToken() {
      const fromUrl = getUrlLaunchToken()
      if (typeof fromUrl === 'string' && fromUrl.trim()) return fromUrl.trim()
      const fromCtx = bridgeContext?.launch_token
      return typeof fromCtx === 'string' && fromCtx.trim() ? fromCtx.trim() : ''
    }

    function appSlug() {
      const slug = bridgeContext?.app_slug
      return typeof slug === 'string' ? slug : ''
    }

    function publisherBackendBase() {
      const fromContext = bridgeContext?.publisher_api_base
      if (typeof fromContext === 'string' && fromContext.trim()) {
        return fromContext.trim().replace(/\/$/, '')
      }
      if (manifestApiUrls.length) return manifestApiUrls[0]
      return ''
    }

    function scopesGranted() {
      return Array.isArray(bridgeContext?.scopes_granted) ? bridgeContext.scopes_granted : []
    }

    function displayUser() {
      const raw = bridgeContext?.display_user
      if (!raw || raw.id == null) return null
      const name = typeof raw.name === 'string' && raw.name.trim() ? raw.name.trim() : String(raw.id)
      return { id: Number(raw.id), name }
    }

    function sessionForPublisherHttp() {
      const slug = appSlug()
      const token = launchToken()
      const base = publisherBackendBase()
      if (!slug || !token || !base) {
        throw new Error(
          'Need app_slug, launch_token, and manifest api_urls (run example/local-bridge-proxy on :51732)',
        )
      }
      return { slug, token, base }
    }

    async function publisherFetch(path, init = {}) {
      const { slug, token, base } = sessionForPublisherHttp()
      const res = await fetch(`${base}${path}`, {
        ...init,
        headers: {
          ...bridgeHeaders(token, slug),
          ...(init.headers || {}),
        },
      })
      const body = await readJson(res)
      if (!res.ok || body?.success === false) {
        throw new Error(body?.error || body?.message || res.statusText || `HTTP ${res.status}`)
      }
      return body?.data ?? body
    }

    function signalHubInboxRefresh() {
      if (global.parent === global) return
      try {
        global.parent.postMessage({ channel: BRIDGE_CHANNEL, event: EVENT_NOTIFY_SENT }, '*')
      } catch {
        /* ignore */
      }
    }

    function requireScope(scope) {
      if (!scopesGranted().includes(scope)) {
        throw new Error(
          `${scope} not on launch token — accept install permissions, then reopen app from Hub`,
        )
      }
    }

    function requireUserReadScope() {
      const scopes = scopesGranted()
      if (!scopes.includes('user.read') && !scopes.includes('user.profile')) {
        throw new Error(
          'user.read not on launch token — accept install permissions, then reopen app from Hub',
        )
      }
    }

    /** GET {api_urls}/bridge/user */
    async function fetchBridgeUser() {
      requireUserReadScope()
      return publisherFetch('/bridge/user', { method: 'GET' })
    }

    /** POST {api_urls}/bridge/notify */
    async function fetchBridgeNotify(payload) {
      requireScope('desktop.notify')
      const result = await publisherFetch('/bridge/notify', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload ?? {}),
      })
      signalHubInboxRefresh()
      return result
    }

    function callBridge(method, args) {
      const bridgeArgs = Array.isArray(args) ? args : [args]
      return new Promise((resolve, reject) => {
        const id = `bridge-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`
        const onMsg = (event) => {
          const msg = event.data
          if (!msg || msg.channel !== BRIDGE_CHANNEL || msg.event !== EVENT_RESULT || msg.id !== id) return
          global.removeEventListener('message', onMsg)
          if (msg.ok) resolve(msg.result)
          else reject(new Error(msg.error || 'Bridge error'))
        }
        global.addEventListener('message', onMsg)
        global.parent.postMessage(
          { channel: BRIDGE_CHANNEL, event: EVENT_CALL, id, method, args: bridgeArgs },
          '*',
        )
      })
    }

    function requestBridgeReady() {
      if (global.parent === global) return
      global.parent.postMessage({ channel: BRIDGE_CHANNEL, event: EVENT_PING }, '*')
    }

    function handleBridgeMessage(data) {
      if (!data || data.channel !== BRIDGE_CHANNEL || data.event !== EVENT_READY) return false
      bridgeReady = true
      bridgeContext = data.context ?? null
      const pubBase = bridgeContext?.publisher_api_base
      if (typeof pubBase === 'string' && pubBase.trim() && !manifestApiUrls.length) {
        manifestApiUrls = [pubBase.trim().replace(/\/$/, '')]
      }
      return true
    }

    function statusLine() {
      const base = publisherBackendBase() || '(api_urls + local-bridge-proxy :51732)'
      return displayUser()
        ? `Bridge ready — publisher HTTP: ${base}/bridge/user | ${base}/bridge/notify`
        : `Bridge ready — publisher HTTP via ${base}`
    }

    /**
     * @param {unknown} err
     * @param {'user' | 'notify'} kind
     */
    function formatHttpError(err, kind) {
      const msg = err instanceof Error ? err.message : String(err)
      const base = publisherBackendBase() || 'publisher backend'
      const path = kind === 'notify' ? '/bridge/notify' : '/bridge/user'
      if (msg === 'Failed to fetch') {
        return `Blocked calling ${base}${path} — run local-bridge-proxy on :51732`
      }
      if (msg.includes('App Hub API not found') || msg.includes('Invalid or expired')) {
        return msg
      }
      if (kind === 'user' && !msg.includes('proxy')) {
        return `${msg} — check proxy APPHUB_BACKEND_URL (hub-host .env)`
      }
      return msg
    }

    return {
      BRIDGE_CHANNEL,
      EVENT_READY,
      EVENT_NOTIFY_SENT,
      get ready() {
        return bridgeReady
      },
      get context() {
        return bridgeContext
      },
      setManifestApiUrls,
      normalizeApiUrls,
      publisherBackendBase,
      displayUser,
      scopesGranted,
      launchToken,
      statusLine,
      fetchBridgeUser,
      fetchBridgeNotify,
      callBridge,
      requestBridgeReady,
      handleBridgeMessage,
      formatHttpError,
    }
  }

  global.AppHubPublisherBridge = {
    BRIDGE_CHANNEL,
    EVENT_NOTIFY_SENT,
    normalizeApiUrls,
    createPublisherBridge,
  }
})(typeof globalThis !== 'undefined' ? globalThis : window)
