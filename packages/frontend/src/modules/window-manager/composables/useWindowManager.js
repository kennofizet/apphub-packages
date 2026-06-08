import { computed, inject, markRaw, reactive } from 'vue'
import {
  clampWindowToWorkArea,
  fullscreenBounds,
  layoutSnapshot,
  miniBoundsFromStorage,
  resolveOpenLayout,
  saveWindowLayout,
  setDesktopWorkArea,
} from '../utils/windowLayout.js'
import { layoutFromSession } from '../utils/sessionLayout.js'

export const WINDOW_MANAGER_KEY = 'apphubWindowManager'

/**
 * Independent window manager — open, close, focus, minimize app windows on the desktop.
 */
export function createWindowManagerState() {
  const state = reactive({
    windows: [],
    activeId: null,
    nextZ: 10,
  })

  function bringToFront(id) {
    state.nextZ += 1
    const win = state.windows.find((w) => w.id === id)
    if (win) {
      win.zIndex = state.nextZ
      win.minimized = false
    }
    state.activeId = id
  }

  function persistLayout(win) {
    if (!win?.layoutKey) return
    saveWindowLayout(win.layoutKey, layoutSnapshot(win))
  }

  function openWindow(definition, sessionState = null) {
    const existing = state.windows.find((w) => w.id === definition.id)
    if (existing) {
      bringToFront(definition.id)
      return existing
    }

    const layout = sessionState
      ? layoutFromSession(sessionState, definition)
      : resolveOpenLayout({
          ...definition,
          offsetIndex: state.windows.length,
        })

    const zIndex = sessionState?.zIndex ?? state.nextZ + 1
    state.nextZ = Math.max(state.nextZ, zIndex)

    const entry = {
      id: definition.id,
      title: definition.title,
      icon: definition.icon ?? '📦',
      component: definition.component ? markRaw(definition.component) : null,
      props: markRaw({ ...(definition.props ?? {}) }),
      width: layout.width,
      height: layout.height,
      x: layout.x,
      y: layout.y,
      display: layout.display,
      layoutKey: layout.layoutKey,
      miniWidth: layout.miniWidth,
      miniHeight: layout.miniHeight,
      zIndex,
      minimized: !!sessionState?.minimized,
    }
    state.windows.push(entry)
    if (!sessionState) {
      state.activeId = entry.id
    }
    return entry
  }

  function finishSessionRestore(activeId) {
    if (activeId && state.windows.some((w) => w.id === activeId)) {
      state.activeId = activeId
      const win = state.windows.find((w) => w.id === activeId)
      if (win && !win.minimized) {
        state.nextZ += 1
        win.zIndex = state.nextZ
      }
    } else {
      const top = [...state.windows]
        .filter((w) => !w.minimized)
        .sort((a, b) => b.zIndex - a.zIndex)[0]
      state.activeId = top?.id ?? null
    }
    syncFullscreenWindows()
    relayoutWindows()
  }

  function closeWindow(id) {
    const idx = state.windows.findIndex((w) => w.id === id)
    if (idx === -1) return
    const win = state.windows[idx]
    persistLayout(win)
    state.windows.splice(idx, 1)
    if (state.activeId === id) {
      const top = state.windows[state.windows.length - 1]
      state.activeId = top?.id ?? null
    }
  }

  function minimizeWindow(id) {
    const win = state.windows.find((w) => w.id === id)
    if (!win) return
    win.minimized = true
    if (state.activeId === id) {
      const visible = [...state.windows].filter((w) => !w.minimized).sort((a, b) => b.zIndex - a.zIndex)
      state.activeId = visible[0]?.id ?? null
    }
  }

  function focusWindow(id) {
    bringToFront(id)
  }

  /**
   * Leave fullscreen and place the window so the title-bar grab point stays under the pointer.
   * pointerX/pointerY and offsetX/offsetY are in work-area coordinates.
   */
  function restoreWindowFromFullscreen(id, { pointerX, pointerY, offsetX, offsetY }) {
    const win = state.windows.find((w) => w.id === id)
    if (!win?.layoutKey || win.display !== 'fullscreen') return false

    win.display = 'mini'
    win.width = win.miniWidth ?? 820
    win.height = win.miniHeight ?? 520
    win.x = pointerX - offsetX
    win.y = pointerY - offsetY
    clampWindowToWorkArea(win)
    persistLayout(win)
    bringToFront(id)
    return true
  }

  function toggleWindowDisplay(id) {
    const win = state.windows.find((w) => w.id === id)
    if (!win?.layoutKey) return

    if (win.display === 'fullscreen') {
      const bounds = miniBoundsFromStorage(win.layoutKey, win)
      win.display = 'mini'
      win.width = bounds.width
      win.height = bounds.height
      win.x = bounds.x
      win.y = bounds.y
    } else {
      saveWindowLayout(win.layoutKey, {
        display: 'fullscreen',
        mini: {
          x: win.x,
          y: win.y,
          width: win.width,
          height: win.height,
        },
      })
      Object.assign(win, fullscreenBounds())
      win.display = 'fullscreen'
    }

    persistLayout(win)
    bringToFront(id)
  }

  function saveWindowLayoutState(id) {
    const win = state.windows.find((w) => w.id === id)
    if (!win?.layoutKey || win.display !== 'mini') return
    persistLayout(win)
  }

  function setWorkArea(size) {
    setDesktopWorkArea(size)
    syncFullscreenWindows()
  }

  function syncFullscreenWindows() {
    const bounds = fullscreenBounds()
    state.windows.forEach((win) => {
      if (win.display === 'fullscreen') {
        Object.assign(win, bounds)
      }
    })
  }

  function relayoutWindows() {
    state.windows.forEach((win) => {
      if (win.display === 'fullscreen') {
        Object.assign(win, fullscreenBounds())
        return
      }
      const area = fullscreenBounds()
      const maxX = Math.max(0, area.width - win.width)
      const maxY = Math.max(0, area.height - win.height)
      win.x = Math.min(Math.max(0, win.x), maxX)
      win.y = Math.min(Math.max(0, win.y), maxY)
    })
  }

  const visibleWindows = computed(() => state.windows.filter((w) => !w.minimized))

  const taskbarWindows = computed(() => [...state.windows].sort((a, b) => a.title.localeCompare(b.title)))

  return {
    state,
    visibleWindows,
    taskbarWindows,
    openWindow,
    finishSessionRestore,
    closeWindow,
    minimizeWindow,
    focusWindow,
    toggleWindowDisplay,
    restoreWindowFromFullscreen,
    saveWindowLayoutState,
    setWorkArea,
    syncFullscreenWindows,
    relayoutWindows,
  }
}

export function provideWindowManager(app, manager) {
  app.provide(WINDOW_MANAGER_KEY, manager)
}

export function useWindowManager() {
  const manager = inject(WINDOW_MANAGER_KEY, null)
  if (!manager) {
    throw new Error('useWindowManager() requires installAppHubModule() or provideWindowManager()')
  }
  return manager
}
