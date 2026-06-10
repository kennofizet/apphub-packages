import { inject, reactive } from 'vue'

export const DESKTOP_NOTIFICATIONS_KEY = Symbol('apphubDesktopNotifications')

/**
 * OS-style toast stack for the Hub desktop (reusable by shell, drop install, bridge later).
 */
export function createDesktopNotificationsState() {
  const state = reactive({
    items: [],
  })

  let seq = 0

  function dismiss(id) {
    const idx = state.items.findIndex((item) => item.id === id)
    if (idx !== -1) state.items.splice(idx, 1)
  }

  /**
   * @param {{
   *   title?: string,
   *   message: string,
   *   type?: 'info' | 'success' | 'error' | 'warning',
   *   duration?: number,
   * }} payload
   */
  function push(payload) {
    const message = String(payload?.message ?? '').trim()
    const title = String(payload?.title ?? '').trim()
    if (!message && !title) return null

    const type = payload?.type === 'success'
      || payload?.type === 'error'
      || payload?.type === 'warning'
      ? payload.type
      : 'info'

    const id = `notif-${++seq}`
    const item = {
      id,
      title,
      message,
      type,
      duration: typeof payload?.duration === 'number' ? payload.duration : 6000,
    }

    state.items.push(item)

    if (item.duration > 0) {
      setTimeout(() => dismiss(id), item.duration)
    }

    return id
  }

  function success(message, title = '') {
    return push({ type: 'success', title, message })
  }

  function error(message, title = '') {
    return push({ type: 'error', title, message })
  }

  function info(message, title = '') {
    return push({ type: 'info', title, message })
  }

  function warning(message, title = '') {
    return push({ type: 'warning', title, message })
  }

  return {
    state,
    push,
    dismiss,
    success,
    error,
    info,
    warning,
  }
}

export function useDesktopNotifications() {
  return inject(DESKTOP_NOTIFICATIONS_KEY, null)
}
