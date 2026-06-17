import {
  BRIDGE_CHANNEL,
  BRIDGE_EVENT_CALL,
  BRIDGE_EVENT_PING,
  BRIDGE_EVENT_READY,
  BRIDGE_EVENT_RESULT,
  BRIDGE_METHODS,
} from './constants.js'
import { scopeToRequestForMethod, isMethodScopeGranted } from './scopeRequirements.js'

const USER_SCOPES = new Set(['user.read', 'user.profile'])

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
 *   bridgeDesktopMessage?: (token: string, slug: string, payload: object) => Promise<unknown>,
 *   requestScopeConsent: (scope: string) => Promise<boolean>,
 *   onSessionScopeGranted?: (scope: string) => void,
 *   onDesktopMessage?: (payload: object) => void,
 *   onNotify?: (payload: { title?: string, body?: string, icon?: string }) => void,
 *   onTaskbarBadge?: (count: number | null) => void,
 *   getEntryOrigin?: () => string,
 *   getDisplayUser?: () => { id: number | string, name?: string | null } | null,
 *   getBridgeApiBase?: () => string | null,
 *   getPublisherApiBase?: () => string | null,
 *   getManifestPermissions?: () => string[],
 *   isOpaqueHostedSandbox?: () => boolean,
 * }} options
 */
export function createRunnerBridgeHost(options) {
  /** Server minted scopes on launch token — never mutated here. */
  let tokenScopes = new Set()
  /** Hub session desktop consents (notify, message, badge) — UI only. */
  let sessionGranted = new Set()
  let stopped = false

  function syncTokenScopesFromContext() {
    const ctx = options.getLaunchContext?.()
    tokenScopes = new Set(Array.isArray(ctx?.scopes_granted) ? ctx.scopes_granted : [])
  }

  function manifestPermissions() {
    const raw = options.getManifestPermissions?.() ?? []
    return Array.isArray(raw) ? raw : []
  }

  function isScopeDeclared(scope) {
    return manifestPermissions().includes(scope)
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
    syncTokenScopesFromContext()
    const ctx = options.getLaunchContext?.() ?? {}
    const displayUser = resolveDisplayUser()
    const permissions = manifestPermissions()
    postToFrame({
      channel: BRIDGE_CHANNEL,
      event: BRIDGE_EVENT_READY,
      context: {
        app_slug: options.appSlug,
        session_id: ctx.session_id ?? null,
        scopes_granted: [...tokenScopes],
        session_granted: [...sessionGranted],
        permissions,
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

  async function ensureSessionScopeGranted(scope) {
    const normalized = String(scope ?? '').trim()
    if (!normalized) return false
    if (!isScopeDeclared(normalized)) return false
    if (USER_SCOPES.has(normalized)) {
      return tokenScopes.has(normalized)
    }
    if (sessionGranted.has(normalized)) return true

    const ok = await options.requestScopeConsent(normalized)
    if (!ok) return false

    sessionGranted.add(normalized)
    options.onSessionScopeGranted?.(normalized)
    return true
  }

  async function ensureMethodScopeGranted(method) {
    if (isMethodScopeGranted(method, sessionGranted) || isMethodScopeGranted(method, tokenScopes)) {
      return true
    }
    const scope = scopeToRequestForMethod(method)
    if (!scope) return false
    return ensureSessionScopeGranted(scope)
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
        if (!isScopeDeclared(scope)) {
          reply(id, true, false)
          return
        }
        if (USER_SCOPES.has(scope)) {
          reply(id, true, tokenScopes.has(scope))
          return
        }
        const granted = await ensureSessionScopeGranted(scope)
        reply(id, true, granted)
        return
      }

      if (method === 'sendDesktopMessage') {
        if (!await ensureMethodScopeGranted(method)) {
          reply(id, false, null, 'Scope not granted')
          return
        }
        const payload = args?.[0] ?? {}
        await options.bridgeDesktopMessage?.(token, slug, payload)
        options.onDesktopMessage?.(payload)
        reply(id, true, undefined)
        return
      }

      if (method === 'notify') {
        if (!await ensureMethodScopeGranted(method)) {
          reply(id, false, null, 'Scope not granted')
          return
        }
        const payload = args?.[0] ?? {}
        options.onNotify?.(payload)
        reply(id, true, undefined)
        return
      }

      if (method === 'setTaskbarBadge') {
        if (!await ensureMethodScopeGranted(method)) {
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
