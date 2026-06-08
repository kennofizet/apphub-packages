import { safeParseJson, sanitizeWindowLayout } from '../../../utils/safeStorage.js'

export const TASKBAR_HEIGHT = 48
export const WINDOW_MIN_WIDTH = 280
export const WINDOW_MIN_HEIGHT = 200
const STORAGE_PREFIX = 'apphub-window-layout:'

/** Measured from `.apphub-desktop__workarea` — not the browser viewport. */
let desktopWorkArea = { width: 0, height: 0 }

export function setDesktopWorkArea(area) {
  desktopWorkArea = {
    width: Math.max(0, area?.width ?? 0),
    height: Math.max(0, area?.height ?? 0),
  }
}

export function getWorkArea() {
  if (desktopWorkArea.width > 0 && desktopWorkArea.height > 0) {
    return { ...desktopWorkArea }
  }
  return {
    width: window.innerWidth,
    height: Math.max(200, window.innerHeight - TASKBAR_HEIGHT),
  }
}

export function fullscreenBounds() {
  const area = getWorkArea()
  return { x: 0, y: 0, width: area.width, height: area.height }
}

export function centerWindow(width, height) {
  const area = getWorkArea()
  return {
    x: Math.max(0, Math.round((area.width - width) / 2)),
    y: Math.max(0, Math.round((area.height - height) / 2)),
  }
}

export function clampWindowToWorkArea(win) {
  const { width: areaW, height: areaH } = getWorkArea()
  win.x = Math.min(Math.max(0, win.x), Math.max(0, areaW - win.width))
  win.y = Math.min(Math.max(0, win.y), Math.max(0, areaH - win.height))
}

export function clampWindowDimensions(win) {
  const area = getWorkArea()
  const minW = Math.min(win.miniWidth ?? WINDOW_MIN_WIDTH, area.width)
  const minH = Math.min(win.miniHeight ?? WINDOW_MIN_HEIGHT, area.height)

  win.width = Math.max(minW, Math.min(area.width, win.width))
  win.height = Math.max(minH, Math.min(area.height, win.height))
  clampWindowToWorkArea(win)
}

/** @param {'n'|'s'|'e'|'w'|'ne'|'nw'|'se'|'sw'} edge */
export function applyWindowResize(win, edge, dx, dy) {
  const area = getWorkArea()
  const minW = Math.min(win.miniWidth ?? WINDOW_MIN_WIDTH, area.width)
  const minH = Math.min(win.miniHeight ?? WINDOW_MIN_HEIGHT, area.height)

  let { x, y, width, height } = win
  const right = x + width
  const bottom = y + height

  if (edge.includes('e')) width += dx
  if (edge.includes('w')) {
    x += dx
    width -= dx
  }
  if (edge.includes('s')) height += dy
  if (edge.includes('n')) {
    y += dy
    height -= dy
  }

  width = Math.max(minW, Math.min(area.width, width))
  height = Math.max(minH, Math.min(area.height, height))

  if (edge.includes('w')) x = right - width
  if (edge.includes('n')) y = bottom - height

  x = Math.max(0, Math.min(x, area.width - width))
  y = Math.max(0, Math.min(y, area.height - height))

  win.x = x
  win.y = y
  win.width = width
  win.height = height
}

export function loadWindowLayout(layoutKey) {
  if (!layoutKey || typeof layoutKey !== 'string' || layoutKey.length > 80) return null
  try {
    const raw = localStorage.getItem(STORAGE_PREFIX + layoutKey)
    const parsed = safeParseJson(raw, 32 * 1024)
    return parsed ? sanitizeWindowLayout(parsed) : null
  } catch {
    return null
  }
}

export function saveWindowLayout(layoutKey, layout) {
  if (!layoutKey) return
  try {
    localStorage.setItem(STORAGE_PREFIX + layoutKey, JSON.stringify(layout))
  } catch {
    /* ignore quota / private mode */
  }
}

/**
 * Resolve initial bounds for a new window (fullscreen default, or restored mini position).
 */
export function resolveOpenLayout(definition) {
  const miniWidth = definition.miniWidth ?? definition.width ?? 720
  const miniHeight = definition.miniHeight ?? definition.height ?? 480
  const layoutKey = definition.layoutKey ?? null

  if (!layoutKey) {
    const pos = definition.x != null && definition.y != null
      ? { x: definition.x, y: definition.y }
      : {
          x: 80 + (definition.offsetIndex ?? 0) * 28,
          y: 60 + (definition.offsetIndex ?? 0) * 28,
        }
    return {
      display: 'mini',
      layoutKey: null,
      miniWidth,
      miniHeight,
      width: definition.width ?? miniWidth,
      height: definition.height ?? miniHeight,
      ...pos,
    }
  }

  const saved = loadWindowLayout(layoutKey)
  const defaultDisplay = definition.defaultDisplay ?? 'fullscreen'

  if (saved?.display === 'mini') {
    const mini = saved.mini ?? saved
    const width = mini.width ?? miniWidth
    const height = mini.height ?? miniHeight
    const hasPosition = mini.x != null && mini.y != null
    const pos = hasPosition ? { x: mini.x, y: mini.y } : centerWindow(width, height)
    return {
      display: 'mini',
      layoutKey,
      miniWidth,
      miniHeight,
      width,
      height,
      ...pos,
    }
  }

  if (saved?.display === 'fullscreen' || defaultDisplay === 'fullscreen') {
    return {
      display: 'fullscreen',
      layoutKey,
      miniWidth,
      miniHeight,
      ...fullscreenBounds(),
    }
  }

  const pos = centerWindow(miniWidth, miniHeight)
  return {
    display: 'mini',
    layoutKey,
    miniWidth,
    miniHeight,
    width: miniWidth,
    height: miniHeight,
    ...pos,
  }
}

function readMiniBounds(saved, fallback) {
  const mini = saved?.mini ?? saved
  return {
    x: mini?.x ?? null,
    y: mini?.y ?? null,
    width: mini?.width ?? fallback.width,
    height: mini?.height ?? fallback.height,
  }
}

export function layoutSnapshot(win) {
  const fallback = {
    width: win.miniWidth ?? win.width,
    height: win.miniHeight ?? win.height,
  }

  if (win.display === 'fullscreen') {
    const existing = loadWindowLayout(win.layoutKey)
    const mini = readMiniBounds(existing, fallback)
    return {
      display: 'fullscreen',
      mini: {
        x: mini.x,
        y: mini.y,
        width: mini.width,
        height: mini.height,
      },
    }
  }

  return {
    display: 'mini',
    mini: {
      x: win.x,
      y: win.y,
      width: win.width,
      height: win.height,
    },
  }
}

export function miniBoundsFromStorage(layoutKey, win) {
  const saved = loadWindowLayout(layoutKey)
  const fallback = {
    width: win.miniWidth ?? 820,
    height: win.miniHeight ?? 520,
  }
  const mini = readMiniBounds(saved, fallback)
  const hasPosition = mini.x != null && mini.y != null
  const pos = hasPosition ? { x: mini.x, y: mini.y } : centerWindow(mini.width, mini.height)
  return {
    width: mini.width,
    height: mini.height,
    ...pos,
  }
}
