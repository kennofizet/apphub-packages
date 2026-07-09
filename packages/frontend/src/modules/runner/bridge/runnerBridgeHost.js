import {
  BRIDGE_CHANNEL,
  BRIDGE_EVENT_CALL,
  BRIDGE_EVENT_PING,
  BRIDGE_EVENT_READY,
  BRIDGE_EVENT_RESULT,
  BRIDGE_METHODS,
} from './constants.js'
import {
  findParentBridgeAction,
  findParentBridgeEvent,
  normalizeParentBridgeCatalog,
} from './parentBridgeCatalog.js'
import { draftParentBridgeFixture } from './parentBridgeDraftFixtures.js'
import {
  assertParentBridgePayloadSize,
  isValidParentBridgeActionName,
  isValidParentBridgeEventName,
} from './parentBridgeSecurity.js'
import { scopeToRequestForMethod, isMethodScopeGranted } from './scopeRequirements.js'
import { isParentBridgeScope } from '../../../utils/appBridgeScopes.js'

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
 *   requestScopeConsent: (scope: string) => Promise<boolean>,
 *   onSessionScopeGranted?: (scope: string) => void,
 *   onDesktopMessage?: (payload: object) => void,
 *   onPublisherNotifySent?: () => void,
 *   onTaskbarBadge?: (count: number | null) => void,
 *   reportUsageError?: (metadata: Record<string, unknown>) => Promise<void>,
 *   getEntryOrigin?: () => string,
 *   getDisplayUser?: () => { id: number | string, name?: string | null } | null,
 *   getBridgeApiBase?: () => string | null,
 *   getPublisherApiBase?: () => string | null,
 *   getManifestPermissions?: () => string[],
 *   getAppVersion?: () => string | null,
 *   getHubLocale?: () => string | null,
 *   getColorScheme?: () => string | null,
 *   isOpaqueHostedSandbox?: () => boolean,
 *   getParentBridgeCatalog?: () => object | null,
 *   isDraftApp?: () => boolean,
 *   forwardParentCall?: (id: string, action: string, args: object, meta: object) => Promise<unknown>,
 *   forwardParentEvent?: (name: string, payload: object, meta: object) => void,
 *   hasProductParent?: () => boolean,
 * }} options
 */
export function createRunnerBridgeHost(options) {
  /** Server minted scopes on launch token — never mutated here. */
  let tokenScopes = new Set()
  /** Hub session desktop consents (message, badge) — UI only. */
  let sessionGranted = new Set()
  let stopped = false

  function isDraftApp() {
    return options.isDraftApp?.() === true
  }

  /** Soft failures where draft fixtures may stand in for real parent data. */
  function isParentBridgeSoftFail(message) {
    const msg = String(message ?? '')
    return (
      msg === 'Scope not granted'
      || msg === 'PARENT_UNAVAILABLE'
      || msg === 'PARENT_TIMEOUT'
      || msg === 'SCOPE_NOT_GRANTED'
      || msg === 'FORBIDDEN'
      || msg === 'NOT_IMPLEMENTED'
      || msg.includes('Install consent')
      || msg.includes('Permission denied')
      || msg.includes('Parent bridge')
    )
  }

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
    const publisherApiBase = options.getPublisherApiBase?.() ?? null
    const hasPublisherBridge = typeof publisherApiBase === 'string' && publisherApiBase.trim() !== ''
    const parentBridge = normalizeParentBridgeCatalog(options.getParentBridgeCatalog?.())
    postToFrame({
      channel: BRIDGE_CHANNEL,
      event: BRIDGE_EVENT_READY,
      context: {
        app_slug: options.appSlug,
        session_id: ctx.session_id ?? null,
        scopes_granted: [...tokenScopes],
        session_granted: [...sessionGranted],
        permissions,
        ...(hasPublisherBridge && ctx.launch_token ? { launch_token: ctx.launch_token } : {}),
        bridge_api_base: options.getBridgeApiBase?.() ?? null,
        publisher_api_base: publisherApiBase,
        caller_origin: options.getEntryOrigin?.() ?? null,
        app_version: options.getAppVersion?.() ?? null,
        hub_locale: options.getHubLocale?.() ?? null,
        color_scheme: options.getColorScheme?.() ?? null,
        ...(parentBridge.available ? { parent_bridge: parentBridge } : {}),
        ...(tokenScopes.has('user.read') && displayUser ? { display_user: displayUser } : {}),
      },
    })
  }

  function isTrustedSource(event) {
    const frame = options.getIframe?.()
    if (!frame?.contentWindow) return false
    if (event.source !== frame.contentWindow) return false

    if (options.isOpaqueHostedSandbox?.()) {
      return true
    }

    const entryOrigin = options.getEntryOrigin?.() ?? ''
    if (!entryOrigin) return false

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

const SAVE_FILE_MAX_BYTES = 52_428_800

  function sanitizeDownloadFilename(raw) {
    const base = String(raw ?? 'download').replace(/\\/g, '/').split('/').pop() ?? 'download'
    const trimmed = base.trim().slice(0, 255)
    return trimmed || 'download'
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
        if (USER_SCOPES.has(scope) || isParentBridgeScope(scope)) {
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
        const title = String(payload?.title ?? '').trim().slice(0, 255)
        const body = String(payload?.body ?? '').trim().slice(0, 2000)
        options.onDesktopMessage?.({ ...payload, title, body })
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

      if (method === 'reportError') {
        if (!await ensureMethodScopeGranted(method)) {
          reply(id, false, null, 'Scope not granted')
          return
        }
        const raw = args?.[0]
        const metadata = raw && typeof raw === 'object' && !Array.isArray(raw)
          ? raw
          : { message: String(raw ?? 'Unknown error') }
        await options.reportUsageError?.(metadata)
        reply(id, true, undefined)
        return
      }

      if (method === 'saveFile') {
        if (!await ensureMethodScopeGranted(method)) {
          reply(id, false, null, 'Scope not granted')
          return
        }
        const payload = args?.[0] ?? {}
        const filename = sanitizeDownloadFilename(payload?.filename)
        const mime = String(payload?.mime ?? 'application/octet-stream').trim().slice(0, 127)
        const data = payload?.data
        let bytes = null
        if (data instanceof ArrayBuffer) {
          bytes = new Uint8Array(data)
        } else if (ArrayBuffer.isView(data)) {
          bytes = new Uint8Array(data.buffer, data.byteOffset, data.byteLength)
        } else if (typeof data === 'string') {
          try {
            const normalized = data.replace(/^data:[^;]+;base64,/, '')
            const binary = atob(normalized)
            bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0))
          } catch {
            reply(id, false, null, 'Invalid base64 data')
            return
          }
        } else {
          reply(id, false, null, 'data required (base64 string or ArrayBuffer)')
          return
        }
        if (bytes.byteLength > SAVE_FILE_MAX_BYTES) {
          reply(id, false, null, 'File exceeds maximum size (50 MB)')
          return
        }
        const blob = new Blob([bytes], { type: mime || 'application/octet-stream' })
        const objectUrl = URL.createObjectURL(blob)
        const anchor = document.createElement('a')
        anchor.href = objectUrl
        anchor.download = filename
        anchor.style.display = 'none'
        document.body.appendChild(anchor)
        anchor.click()
        anchor.remove()
        setTimeout(() => URL.revokeObjectURL(objectUrl), 1000)
        reply(id, true, { saved: true, filename })
        return
      }

      if (method === 'callParent') {
        const action = String(args?.[0] ?? '').trim()
        const actionArgs = args?.[1]
        const callOptions = args?.[2] && typeof args[2] === 'object' && !Array.isArray(args[2])
          ? args[2]
          : {}
        const normalizedArgs = actionArgs && typeof actionArgs === 'object' && !Array.isArray(actionArgs)
          ? actionArgs
          : {}
        // forceReal: skip draft Hub fixtures and always use real parent / strict errors
        const allowDraftFixture = isDraftApp() && callOptions.forceReal !== true

        if (!action) {
          reply(id, false, null, 'Action required')
          return
        }

        if (!isValidParentBridgeActionName(action)) {
          reply(id, false, null, 'ACTION_NOT_ALLOWED')
          return
        }

        const catalog = normalizeParentBridgeCatalog(options.getParentBridgeCatalog?.())
        const entry = findParentBridgeAction(catalog, action)
        if (!entry) {
          reply(id, false, null, 'ACTION_NOT_ALLOWED')
          return
        }

        if (!tokenScopes.has(entry.scope)) {
          if (allowDraftFixture) {
            reply(id, true, draftParentBridgeFixture(action))
            return
          }
          reply(id, false, null, 'Scope not granted')
          return
        }

        if (!options.hasProductParent?.()) {
          if (allowDraftFixture) {
            reply(id, true, draftParentBridgeFixture(action))
            return
          }
          reply(id, false, null, 'PARENT_UNAVAILABLE')
          return
        }

        if (!options.forwardParentCall) {
          if (allowDraftFixture) {
            reply(id, true, draftParentBridgeFixture(action))
            return
          }
          reply(id, false, null, 'PARENT_UNAVAILABLE')
          return
        }

        try {
          assertParentBridgePayloadSize(normalizedArgs)
        } catch {
          reply(id, false, null, 'Payload too large')
          return
        }

        try {
          const result = await options.forwardParentCall(id, action, normalizedArgs, {
            app_slug: slug,
            session_id: ctx.session_id ?? null,
            bridge_scope: entry.scope,
          })
          reply(id, true, result)
        } catch (err) {
          const message = err instanceof Error ? err.message : 'Bridge error'
          if (allowDraftFixture && isParentBridgeSoftFail(message)) {
            reply(id, true, draftParentBridgeFixture(action))
            return
          }
          reply(id, false, null, message)
        }
        return
      }

      if (method === 'emitToParent') {
        const eventName = String(args?.[0] ?? '').trim()
        const payload = args?.[1]
        const normalizedPayload = payload && typeof payload === 'object' && !Array.isArray(payload)
          ? payload
          : {}

        if (!eventName) {
          reply(id, false, null, 'Event name required')
          return
        }

        if (!isValidParentBridgeEventName(eventName)) {
          reply(id, false, null, 'ACTION_NOT_ALLOWED')
          return
        }

        const catalog = normalizeParentBridgeCatalog(options.getParentBridgeCatalog?.())
        const entry = findParentBridgeEvent(catalog, eventName)
        if (!entry) {
          reply(id, false, null, 'ACTION_NOT_ALLOWED')
          return
        }

        if (!tokenScopes.has(entry.scope)) {
          if (isDraftApp()) {
            reply(id, true, undefined)
            return
          }
          reply(id, false, null, 'Scope not granted')
          return
        }

        if (!options.hasProductParent?.() || !options.forwardParentEvent) {
          if (isDraftApp()) {
            reply(id, true, undefined)
            return
          }
          reply(id, false, null, 'PARENT_UNAVAILABLE')
          return
        }

        try {
          assertParentBridgePayloadSize(normalizedPayload)
        } catch {
          reply(id, false, null, 'Payload too large')
          return
        }

        options.forwardParentEvent(eventName, normalizedPayload, {
          app_slug: slug,
          bridge_scope: entry.scope,
          session_id: ctx.session_id ?? null,
        })
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
    if (bridgeEvent === 'apphub:publisher:notify-sent') {
      syncTokenScopesFromContext()
      if (tokenScopes.has('desktop.notify')) {
        options.onPublisherNotifySent?.()
      }
      return
    }
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
