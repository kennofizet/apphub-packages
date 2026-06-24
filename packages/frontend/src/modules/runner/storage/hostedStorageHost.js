import {
  HOSTED_STORAGE_CHANNEL,
  HOSTED_STORAGE_LS_PREFIX,
  HOSTED_STORAGE_OP_CLEAR,
  HOSTED_STORAGE_OP_HYDRATE,
  HOSTED_STORAGE_OP_REMOVE,
  HOSTED_STORAGE_OP_SET,
  HOSTED_STORAGE_OP_SNAPSHOT,
} from './hostedStorageConstants.js'

function isStorageMessage(data) {
  return data && typeof data === 'object' && data.channel === HOSTED_STORAGE_CHANNEL
}

function snapshotKey(userKey, slug) {
  return `${HOSTED_STORAGE_LS_PREFIX}${userKey}:${slug}`
}

function loadSnapshot(userKey, slug) {
  try {
    const raw = localStorage.getItem(snapshotKey(userKey, slug))
    if (!raw) return {}
    const parsed = JSON.parse(raw)
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return {}
    return parsed
  } catch {
    return {}
  }
}

function saveSnapshot(userKey, slug, data) {
  localStorage.setItem(snapshotKey(userKey, slug), JSON.stringify(data))
}

/**
 * Hub-side storage for hosted zip apps (opaque sandbox): persists per user + slug in Hub localStorage.
 *
 * @param {{
 *   getIframe: () => HTMLIFrameElement | null | undefined,
 *   getLaunchContext: () => { session_id?: string } | null,
 *   appSlug: string,
 *   getEntryOrigin?: () => string,
 *   getDisplayUser?: () => { id: number | string } | null,
 *   isOpaqueHostedSandbox?: () => boolean,
 * }} options
 */
export function createHostedStorageHost(options) {
  let stopped = false

  function resolveUserKey() {
    const user = options.getDisplayUser?.()
    if (user?.id != null) return String(user.id)
    const sessionId = options.getLaunchContext?.()?.session_id
    if (sessionId) return `session:${sessionId}`
    return 'anon'
  }

  function postMessageTargetOrigin() {
    if (options.isOpaqueHostedSandbox?.()) return '*'
    const origin = options.getEntryOrigin?.() || '*'
    return origin === '' ? '*' : origin
  }

  function postToFrame(message) {
    const frame = options.getIframe?.()
    const target = frame?.contentWindow
    if (!target) return
    target.postMessage(message, postMessageTargetOrigin())
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

  function sendSnapshot() {
    const slug = options.appSlug
    const data = loadSnapshot(resolveUserKey(), slug)
    postToFrame({
      channel: HOSTED_STORAGE_CHANNEL,
      op: HOSTED_STORAGE_OP_SNAPSHOT,
      slug,
      data,
    })
  }

  function onMessage(event) {
    if (stopped || !isStorageMessage(event.data)) return
    if (!isTrustedSource(event)) return

    const { op, key, value, slug } = event.data
    if (slug && slug !== options.appSlug) return

    const userKey = resolveUserKey()
    const appSlug = options.appSlug

    if (op === HOSTED_STORAGE_OP_HYDRATE) {
      sendSnapshot()
      return
    }

    if (op === HOSTED_STORAGE_OP_SET) {
      if (typeof key !== 'string' || key === '') return
      const snapshot = loadSnapshot(userKey, appSlug)
      snapshot[key] = String(value ?? '')
      saveSnapshot(userKey, appSlug, snapshot)
      return
    }

    if (op === HOSTED_STORAGE_OP_REMOVE) {
      if (typeof key !== 'string' || key === '') return
      const snapshot = loadSnapshot(userKey, appSlug)
      delete snapshot[key]
      saveSnapshot(userKey, appSlug, snapshot)
      return
    }

    if (op === HOSTED_STORAGE_OP_CLEAR) {
      saveSnapshot(userKey, appSlug, {})
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
    sendSnapshot,
  }
}
