import { inject, reactive } from 'vue'
import { loadCachedUnreadCount, saveCachedUnreadCount } from '../utils/notificationUnreadCache.js'

export const USER_NOTIFICATION_CENTER_KEY = Symbol('apphubUserNotificationCenter')

/**
 * Persistent inbox notifications (server) + taskbar drawer.
 */
export function createUserNotificationCenter(options = {}) {
  const getApi = typeof options.getApi === 'function' ? options.getApi : () => null
  const getCacheKey = typeof options.getCacheKey === 'function' ? options.getCacheKey : () => ''
  const desktopNotifications = options.desktopNotifications ?? null

  function resolveCacheKey() {
    return String(getCacheKey() ?? '').trim()
  }

  function hydrateUnreadFromCache() {
    const cached = loadCachedUnreadCount(resolveCacheKey())
    if (typeof cached === 'number') state.unreadCount = cached
  }

  function setUnreadCount(count) {
    if (typeof count !== 'number' || !Number.isFinite(count) || count < 0) return
    state.unreadCount = Math.floor(count)
    saveCachedUnreadCount(resolveCacheKey(), state.unreadCount)
  }

  const state = reactive({
    items: [],
    unreadCount: 0,
    drawerOpen: false,
    loading: false,
    loadingMore: false,
    hasMore: false,
    nextCursor: null,
    bellPulse: false,
    removingIds: [],
  })

  hydrateUnreadFromCache()

  const knownIds = new Set()
  let seeded = false
  const dismissQueue = new Set()
  let dismissTimer = null
  let pollTimer = null
  let visibilityHandler = null

  function markRemoving(ids) {
    const list = Array.isArray(ids) ? ids : [ids]
    for (const id of list) {
      if (!state.removingIds.includes(id)) state.removingIds.push(id)
    }
  }

  function clearRemoving(ids) {
    const drop = new Set(Array.isArray(ids) ? ids : [ids])
    state.removingIds = state.removingIds.filter((id) => !drop.has(id))
  }

  async function refreshSummary() {
    const api = getApi()
    if (!api?.notificationsSummary) return null
    try {
      const res = await api.notificationsSummary()
      const count = res?.data?.data?.unread_count
      if (typeof count === 'number') {
        setUnreadCount(count)
        return count
      }
    } catch {
      /* ignore */
    }
    return null
  }

  /** Poll: cheap unread count only; full inbox when count rises or drawer is open. */
  async function pollInbox() {
    const previous = state.unreadCount
    const next = await refreshSummary()
    if (typeof next !== 'number') return

    if (next > previous) {
      await loadInbox({ reset: true, announce: true })
      return
    }

    if (state.drawerOpen && next !== previous) {
      await loadInbox({ reset: true, announce: false })
    }
  }

  function toastPayloadFromItem(item) {
    const title = String(item.title ?? '').trim()
    const body = String(item.body ?? '').trim()
    const app = String(item.app_name ?? item.app_slug ?? '').trim()
    const message = body || title
    const toastTitle = body ? (title || app) : app
    return { message, title: toastTitle }
  }

  function pushDesktopToasts(items, { staggerMs = 0 } = {}) {
    const list = Array.isArray(items) ? items : []
    if (!list.length || !desktopNotifications?.info) return

    state.bellPulse = true
    window.setTimeout(() => {
      state.bellPulse = false
    }, 2200)

    list.forEach((item, index) => {
      window.setTimeout(() => {
        const { message, title } = toastPayloadFromItem(item)
        if (!message && !title) return
        desktopNotifications.info(message, title)
      }, index * staggerMs)
    })
  }

  function applyIncomingItems(items, { announce = false } = {}) {
    const list = Array.isArray(items) ? items : []
    const freshUnread = []

    for (const item of list) {
      const id = item?.id
      if (id == null) continue
      if (!knownIds.has(id) && !item.read_at && announce) {
        freshUnread.push(item)
      }
      knownIds.add(id)
    }

    if (announce && freshUnread.length > 0) {
      pushDesktopToasts(
        freshUnread.sort((a, b) => String(b.created_at).localeCompare(String(a.created_at))).slice(0, 3),
        { staggerMs: 320 },
      )
    }
  }

  async function loadInbox({ reset = true, announce = false } = {}) {
    const api = getApi()
    if (!api?.notifications) return

    if (reset) {
      state.loading = state.items.length === 0
      state.nextCursor = null
      state.hasMore = false
      if (!seeded) state.items = []
    }

    try {
      const res = await api.notifications({ per_page: 20 })
      const items = res?.data?.data ?? []
      const meta = res?.data?.meta ?? {}

      applyIncomingItems(items, { announce: announce && seeded })

      if (reset) {
        state.items = items
      } else {
        const byId = new Map(state.items.map((row) => [row.id, row]))
        for (const row of items) byId.set(row.id, row)
        state.items = [...byId.values()].sort(
          (a, b) => String(b.created_at).localeCompare(String(a.created_at)),
        )
      }

      state.hasMore = !!meta.has_more
      state.nextCursor = meta.next_cursor ?? null
      if (typeof meta.unread_count === 'number') {
        setUnreadCount(meta.unread_count)
      } else {
        await refreshSummary()
      }
    } finally {
      state.loading = false
      seeded = true
    }
  }

  async function loadMore() {
    const api = getApi()
    if (!api?.notifications || !state.hasMore || state.loadingMore || !state.nextCursor) return

    state.loadingMore = true
    try {
      const res = await api.notifications({ per_page: 20, cursor: state.nextCursor })
      const items = res?.data?.data ?? []
      const meta = res?.data?.meta ?? {}
      applyIncomingItems(items, { announce: true })

      const seen = new Set(state.items.map((row) => row.id))
      for (const row of items) {
        if (!seen.has(row.id)) {
          state.items.push(row)
          seen.add(row.id)
        }
      }

      state.hasMore = !!meta.has_more
      state.nextCursor = meta.next_cursor ?? null
      if (typeof meta.unread_count === 'number') setUnreadCount(meta.unread_count)
    } finally {
      state.loadingMore = false
    }
  }

  async function bootstrapInbox() {
    const api = getApi()
    if (!api?.notifications) return false

    hydrateUnreadFromCache()

    state.loading = state.items.length === 0
    state.nextCursor = null
    state.hasMore = false

    try {
      const summaryUnread = await refreshSummary()
      if (summaryUnread === 0) {
        state.items = []
        seeded = true
        return true
      }

      const res = await api.notifications({ per_page: 20 })
      const items = res?.data?.data ?? []
      const meta = res?.data?.meta ?? {}

      state.items = items
      state.hasMore = !!meta.has_more
      state.nextCursor = meta.next_cursor ?? null
      if (typeof meta.unread_count === 'number') {
        setUnreadCount(meta.unread_count)
      } else {
        await refreshSummary()
      }

      const unreadItems = items
        .filter((row) => !row.read_at)
        .sort((a, b) => String(b.created_at).localeCompare(String(a.created_at)))
        .slice(0, 3)

      for (const item of items) {
        if (item?.id != null) knownIds.add(item.id)
      }
      seeded = true

      if (unreadItems.length > 0) {
        pushDesktopToasts(unreadItems, { staggerMs: 380 })
      }
    } catch {
      return false
    } finally {
      state.loading = false
    }

    return true
  }

  function openDrawer() {
    state.drawerOpen = true
    void loadInbox({ reset: true, announce: false })
  }

  function closeDrawer() {
    state.drawerOpen = false
  }

  function toggleDrawer() {
    if (state.drawerOpen) closeDrawer()
    else openDrawer()
  }

  function scheduleDismissFlush() {
    if (dismissTimer) window.clearTimeout(dismissTimer)
    dismissTimer = window.setTimeout(() => {
      void flushDismissQueue()
    }, 5000)
  }

  async function flushDismissQueue() {
    dismissTimer = null
    if (dismissQueue.size === 0) return

    const ids = [...dismissQueue]
    dismissQueue.clear()
    const api = getApi()
    if (!api?.notificationsDismiss) return

    try {
      const res = await api.notificationsDismiss(ids)
      const count = res?.data?.data?.unread_count
      if (typeof count === 'number') setUnreadCount(count)
      else await refreshSummary()
    } catch {
      await loadInbox({ reset: true, announce: false })
    }
  }

  function dismissItem(id) {
    const numericId = Number(id)
    if (!Number.isFinite(numericId) || numericId < 1) return

    markRemoving([numericId])
    window.setTimeout(() => {
      state.items = state.items.filter((row) => row.id !== numericId)
      clearRemoving([numericId])
      if (state.unreadCount > 0) setUnreadCount(state.unreadCount - 1)
    }, 280)

    dismissQueue.add(numericId)
    scheduleDismissFlush()
  }

  async function readAll() {
    const ids = state.items.map((row) => row.id)
    if (!ids.length) return

    markRemoving(ids)
    const api = getApi()
    window.setTimeout(() => {
      state.items = []
      setUnreadCount(0)
      clearRemoving(ids)
    }, 320)

    try {
      await api?.notificationsReadAll?.()
    } catch {
      await loadInbox({ reset: true, announce: false })
    }
  }

  function startPolling(intervalMs = 60_000) {
    stopPolling()

    const tick = () => {
      if (typeof document !== 'undefined' && document.hidden) return
      void pollInbox()
    }

    pollTimer = window.setInterval(tick, intervalMs)

    if (typeof document !== 'undefined') {
      visibilityHandler = () => {
        if (!document.hidden) void pollInbox()
      }
      document.addEventListener('visibilitychange', visibilityHandler)
    }
  }

  function stopPolling() {
    if (pollTimer) {
      window.clearInterval(pollTimer)
      pollTimer = null
    }
    if (visibilityHandler && typeof document !== 'undefined') {
      document.removeEventListener('visibilitychange', visibilityHandler)
      visibilityHandler = null
    }
  }

  function dispose() {
    stopPolling()
    if (dismissTimer) window.clearTimeout(dismissTimer)
    void flushDismissQueue()
  }

  return {
    state,
    bootstrapInbox,
    loadInbox,
    loadMore,
    refreshSummary,
    openDrawer,
    closeDrawer,
    toggleDrawer,
    dismissItem,
    readAll,
    pollInbox,
    startPolling,
    stopPolling,
    dispose,
  }
}

export function useUserNotificationCenter() {
  return inject(USER_NOTIFICATION_CENTER_KEY, null)
}
