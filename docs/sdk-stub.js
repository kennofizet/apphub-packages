/**
 * App Hub bridge client stub — copy into your app or load via <script>.
 * Works in sandboxed Hub iframes via postMessage (channel apphub:bridge).
 *
 * Usage:
 *   <script src="https://your-cdn/sdk-stub.js"></script>
 *   <script>
 *   window.addEventListener('apphub:bridge:ready', async () => {
 *       const bridge = window.AppHubBridge
 *       const ctx = bridge.getContext() // app_slug, launch_token, bridge_api_base, publisher_api_base, app_version, …
 *       const display = bridge.getDisplayUser() // UI only — from Hub bootstrap
 *       console.log('Hello', display?.name)
 *       // Authoritative user: call your publisher backend GET .../bridge/user
 *       // Notifications: POST .../bridge/notify from your publisher backend (api_urls)
 *     })
 *   </script>
 */
;(function initAppHubBridgeStub(global) {
  const CHANNEL = 'apphub:bridge'
  const EVENT_READY = 'apphub:bridge:ready'
  const EVENT_CALL = 'apphub:bridge:call'
  const EVENT_RESULT = 'apphub:bridge:result'

  let ready = false
  /** @type {Record<string, unknown> | null} */
  let context = null
  const pending = new Map()
  const queue = []

  function dispatchReady() {
    try {
      global.dispatchEvent(new CustomEvent(EVENT_READY, { detail: context }))
    } catch {
      /* ignore */
    }
  }

  function postCall(method, args) {
    return new Promise((resolve, reject) => {
      const run = () => {
        const id = `bridge-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`
        pending.set(id, { resolve, reject })
        global.parent.postMessage(
          { channel: CHANNEL, event: EVENT_CALL, id, method, args },
          '*',
        )
      }
      if (ready) run()
      else queue.push(run)
    })
  }

  function normalizeReportError(error) {
    if (error && typeof error === 'object' && !Array.isArray(error)) {
      const message = typeof error.message === 'string' ? error.message : String(error)
      const name = typeof error.name === 'string' ? error.name : undefined
      const stack = typeof error.stack === 'string' ? error.stack.slice(0, 4000) : undefined
      return { message: message.slice(0, 2000), ...(name ? { name } : {}), ...(stack ? { stack } : {}) }
    }
    return { message: String(error ?? 'Unknown error').slice(0, 2000) }
  }

  global.addEventListener('message', (event) => {
    const msg = event.data
    if (!msg || msg.channel !== CHANNEL) return

    if (msg.event === EVENT_READY) {
      ready = true
      context = msg.context ?? null
      dispatchReady()
      while (queue.length) queue.shift()()
      return
    }

    if (msg.event === EVENT_RESULT && msg.id) {
      const p = pending.get(msg.id)
      if (!p) return
      pending.delete(msg.id)
      if (msg.ok) p.resolve(msg.result)
      else p.reject(new Error(msg.error || 'Bridge error'))
    }
  })

  const bridge = {
    getContext() {
      return context
    },
    /** UI-only { id, name } from Hub bootstrap — not for auth; use publisher backend GET bridge/user. */
    getDisplayUser() {
      const user = context?.display_user
      if (!user || user.id == null) return null
      return user
    },
    requestPermission(scope) {
      return postCall('requestPermission', [scope])
    },
    sendDesktopMessage(payload) {
      return postCall('sendDesktopMessage', [payload])
    },
    setTaskbarBadge(count) {
      return postCall('setTaskbarBadge', [count])
    },
    reportError(error) {
      const payload = normalizeReportError(error)
      return postCall('reportError', [payload])
    },
    saveFile(payload) {
      return postCall('saveFile', [payload])
    },
    callParent(action, args, options) {
      const callArgs = [action, args]
      if (options && typeof options === 'object') callArgs.push(options)
      return postCall('callParent', callArgs)
    },
    emitToParent(event, payload) {
      return postCall('emitToParent', [event, payload])
    },
  }

  global.AppHubBridge = bridge
})(typeof globalThis !== 'undefined' ? globalThis : window)
