/**
 * Product shell listener — embed in your main product app (parent of Hub iframe).
 *
 * Listens for apphub:product messages from the Hub iframe and proxies to
 * POST {backendUrl}/parent-bridge/call|event (session token required).
 *
 * @param {{
 *   hubOrigin: string,
 *   backendUrl: string,
 *   token: string,
 *   getToken?: () => string,
 *   onAppEvent?: (detail: { name: string, payload: object, app_slug?: string }) => void,
 * }} options
 */
export function installAppHubProductBridgeListener(options) {
  const hubOrigin = String(options.hubOrigin ?? '').trim().replace(/\/$/, '')
  const backendUrl = String(options.backendUrl ?? '').trim().replace(/\/$/, '')

  if (!hubOrigin || !backendUrl) {
    return () => {}
  }

  function isTrustedHubSource(event) {
    if (event.origin !== hubOrigin) return false
    if (!event.source || event.source === window) return false
    return true
  }

  async function apiPost(path, body) {
    const token = typeof options.getToken === 'function'
      ? String(options.getToken() ?? '').trim()
      : String(options.token ?? '').trim()

    const res = await fetch(`${backendUrl}${path}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { 'X-Knf-Token': token } : {}),
      },
      credentials: 'include',
      body: JSON.stringify(body ?? {}),
    })

    const json = await res.json().catch(() => ({}))
    if (!res.ok && json?.ok !== true) {
      return {
        ok: false,
        error: json?.error || 'REQUEST_FAILED',
        message: json?.message || res.statusText,
      }
    }

    return json
  }

  async function onMessage(event) {
    if (!isTrustedHubSource(event)) return
    const data = event.data
    if (!data || data.channel !== 'apphub:product') return

    if (data.type === 'bridge-call' && data.id) {
      const appSlug = typeof data.app_slug === 'string' ? data.app_slug.trim() : ''
      const bridgeScope = typeof data.bridge_scope === 'string' ? data.bridge_scope.trim() : ''
      if (!appSlug || !bridgeScope) {
        event.source?.postMessage(
          {
            channel: 'apphub:product',
            type: 'bridge-result',
            id: data.id,
            ok: false,
            error: 'VALIDATION_ERROR',
            message: 'app_slug and bridge_scope required',
          },
          hubOrigin,
        )
        return
      }

      const out = await apiPost('/parent-bridge/call', {
        action: data.action,
        args: data.args ?? {},
        app_slug: appSlug,
        bridge_scope: bridgeScope,
        session_id: typeof data.session_id === 'string' ? data.session_id : null,
      })

      event.source?.postMessage(
        {
          channel: 'apphub:product',
          type: 'bridge-result',
          id: data.id,
          ok: out?.ok === true,
          result: out?.result,
          error: out?.error,
          message: out?.message,
        },
        hubOrigin,
      )
      return
    }

    if (data.type === 'app-event') {
      const appSlug = typeof data.app_slug === 'string' ? data.app_slug.trim() : ''
      const bridgeScope = typeof data.bridge_scope === 'string' ? data.bridge_scope.trim() : ''
      if (!appSlug || !bridgeScope) return

      await apiPost('/parent-bridge/event', {
        name: data.name,
        payload: data.payload ?? {},
        app_slug: appSlug,
        bridge_scope: bridgeScope,
        session_id: typeof data.session_id === 'string' ? data.session_id : null,
      })
      options.onAppEvent?.({
        name: data.name,
        payload: data.payload ?? {},
        app_slug: appSlug,
      })
    }
  }

  window.addEventListener('message', onMessage)

  return () => window.removeEventListener('message', onMessage)
}
