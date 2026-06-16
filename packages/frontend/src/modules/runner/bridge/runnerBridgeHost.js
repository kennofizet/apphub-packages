import {
  BRIDGE_CHANNEL,
  BRIDGE_EVENT_CALL,
  BRIDGE_EVENT_PING,
  BRIDGE_EVENT_READY,
  BRIDGE_EVENT_RESULT,
  BRIDGE_METHODS,
} from './constants.js'
import { scopeToRequestForMethod, isMethodScopeGranted } from './scopeRequirements.js'

function isBridgeMessage(data) {
  return data && typeof data === 'object' && data.channel === BRIDGE_CHANNEL
}

/**
 * Hub-side bridge: postMessage RPC from sandboxed app iframe.
 *
 * @param {{
 *   getIframe: () => HTMLIFrameElement | null | undefined,
 *   getLaunchContext: () => {
 *     launch_token?: string,
 *     session_id?: string,
 *     scopes_granted?: string[],
 *     slug?: string,
 *   } | null,
 *   appSlug: string,
 *   appName: string,
 *   api: {
 *     grantBridgeScope?: (token: string, scope: string) => Promise<unknown>,
 *     bridgeUser?: (token: string, slug: string) => Promise<unknown>,
 *     bridgeDesktopMessage?: (token: string, slug: string, payload: object) => Promise<unknown>,
 *   } | null,
 *   requestScopeConsent: (scope: string) => Promise<boolean>,
 *   onScopeGranted?: (scope: string) => void,
 *   onDesktopMessage?: (payload: object) => void,
 *   onNotify?: (payload: { title?: string, body?: string, icon?: string }) => void,
 *   onTaskbarBadge?: (count: number | null) => void,
 *   getEntryOrigin?: () => string,
 *   getDisplayUser?: () => { id: number | string, name?: string | null } | null,
 *   getBridgeApiBase?: () => string | null,
 *   getPublisherApiBase?: () => string | null,
 *   isOpaqueHostedSandbox?: () => boolean,
 * }} options
 */
export function createRunnerBridgeHost(options) {
  let grantedScopes = new Set()
  let stopped = false

  function syncGrantedFromContext() {
    const ctx = options.getLaunchContext?.()
    grantedScopes = new Set(Array.isArray(ctx?.scopes_granted) ? ctx.scopes_granted : [])
  }

  function postMessageTargetOrigin() {
    if (options.isOpaqueHostedSandbox?.()) {
      return '*'
    }

    const origin = options.getEntryOrigin?.() || '*'
    return origin === '' ? '*' : origin
  }

  function postToFrame(message) {
    const frame = options.getIframe?.()
    const target = frame?.contentWindow
    if (!target) return
    target.postMessage(message, postMessageTargetOrigin())
  }

  function reply(id, ok, result, error) {
    postToFrame({
      channel: BRIDGE_CHANNEL,
      event: BRIDGE_EVENT_RESULT,
      id,
      ok,
      result: ok ? result : undefined,
      error: ok ? undefined : (error || 'Bridge error'),
    })
  }

  function resolveDisplayUser() {
    const raw = options.getDisplayUser?.()
    if (!raw || raw.id == null) return null
    const id = Number(raw.id)
    if (!Number.isFinite(id) || id < 1) return null
    const name = typeof raw.name === 'string' ? raw.name.trim() : ''
    return { id, name: name || String(id) }
  }

  function sendReady() {
    syncGrantedFromContext()
    const ctx = options.getLaunchContext?.() ?? {}
    const displayUser = resolveDisplayUser()
    postToFrame({
      channel: BRIDGE_CHANNEL,
      event: BRIDGE_EVENT_READY,
        context: {
        app_slug: options.appSlug,
        session_id: ctx.session_id ?? null,
        scopes_granted: [...grantedScopes],
        launch_token: ctx.launch_token ?? null,
        bridge_api_base: options.getBridgeApiBase?.() ?? null,
        publisher_api_base: options.getPublisherApiBase?.() ?? null,
        caller_origin: options.getEntryOrigin?.() ?? null,
        ...(displayUser ? { display_user: displayUser } : {}),
      },
    })
  }

  function isTrustedSource(event) {
    const frame = options.getIframe?.()
    if (!frame?.contentWindow) return false
    if (event.source !== frame.contentWindow) return false

    const entryOrigin = options.getEntryOrigin?.() ?? ''
    if (!entryOrigin) return true

    try {
      if (event.origin === 'null' || event.origin === '') return true
      return new URL(entryOrigin).origin === event.origin
    } catch {
      return event.origin === 'null'
    }
  }

  async function ensureScopeGranted(token, scope) {
    const normalized = String(scope ?? '').trim()
    if (!normalized) return false
    if (grantedScopes.has(normalized)) return true

    const ok = await options.requestScopeConsent(normalized)
    if (!ok) return false

    if (!options.api?.grantBridgeScope) {
      throw new Error('Bridge API unavailable')
    }

    await options.api.grantBridgeScope(token, normalized)
    grantedScopes.add(normalized)
    options.onScopeGranted?.(normalized)
    return true
  }

  async function ensureMethodScopeGranted(token, method) {
    if (isMethodScopeGranted(method, grantedScopes)) return true
    const scope = scopeToRequestForMethod(method)
    if (!scope) return false
    return ensureScopeGranted(token, scope)
  }

  async function handleCall(id, method, args) {
    const ctx = options.getLaunchContext?.() ?? {}
    const token = ctx.launch_token
    const slug = options.appSlug

    if (!token) {
      reply(id, false, null, 'Launch session not available')
      return
    }

    try {
      if (method === 'requestPermission') {
        const scope = String(args?.[0] ?? '')
        if (!scope) {
          reply(id, false, null, 'Scope required')
          return
        }
        if (grantedScopes.has(scope)) {
          reply(id, true, true)
          return
        }
        try {
          const granted = await ensureScopeGranted(token, scope)
          reply(id, true, granted)
        } catch (err) {
          reply(id, false, null, err?.message || 'Bridge API unavailable')
        }
        return
      }

      if (method === 'getUserInfo') {
        if (!await ensureMethodScopeGranted(token, method)) {
          reply(id, false, null, 'Scope not granted')
          return
        }
        const res = await options.api?.bridgeUser?.(token, slug)
        const data = res?.data?.data ?? res?.data ?? {}
        reply(id, true, data)
        return
      }

      if (method === 'getProfile') {
        if (!await ensureMethodScopeGranted(token, method)) {
          reply(id, false, null, 'Scope not granted')
          return
        }
        const res = await options.api?.bridgeUser?.(token, slug)
        const data = res?.data?.data ?? res?.data ?? {}
        reply(id, true, data)
        return
      }

      if (method === 'sendDesktopMessage') {
        if (!await ensureMethodScopeGranted(token, method)) {
          reply(id, false, null, 'Scope not granted')
          return
        }
        const payload = args?.[0] ?? {}
        await options.api?.bridgeDesktopMessage?.(token, slug, payload)
        options.onDesktopMessage?.(payload)
        reply(id, true, undefined)
        return
      }

      if (method === 'notify') {
        if (!await ensureMethodScopeGranted(token, method)) {
          reply(id, false, null, 'Scope not granted')
          return
        }
        const payload = args?.[0] ?? {}
        options.onNotify?.(payload)
        reply(id, true, undefined)
        return
      }

      if (method === 'setTaskbarBadge') {
        if (!await ensureMethodScopeGranted(token, method)) {
          reply(id, false, null, 'Scope not granted')
          return
        }
        const count = args?.[0]
        options.onTaskbarBadge?.(count === null || count === undefined ? null : Number(count))
        reply(id, true, undefined)
        return
      }

      reply(id, false, null, `Unknown method: ${method}`)
    } catch (err) {
      reply(id, false, null, err?.message || 'Bridge call failed')
    }
  }

  function onMessage(event) {
    if (stopped || !isBridgeMessage(event.data)) return
    if (!isTrustedSource(event)) return

    const { event: bridgeEvent, id, method, args } = event.data
    if (bridgeEvent === BRIDGE_EVENT_PING) {
      sendReady()
      return
    }
    if (bridgeEvent === BRIDGE_EVENT_CALL && id && BRIDGE_METHODS.has(method)) {
      void handleCall(id, method, args)
    }
  }

  function start() {
    stopped = false
    window.addEventListener('message', onMessage)
  }

  function stop() {
    stopped = true
    window.removeEventListener('message', onMessage)
  }

  return {
    start,
    stop,
    sendReady,
  }
}
