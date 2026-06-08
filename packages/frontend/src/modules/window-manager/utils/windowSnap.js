import { centerWindow, fullscreenBounds, getWorkArea } from './windowLayout.js'

/** @typedef {'left'|'right'|'up'|'down'} SnapDirection */

/**
 * Windows-style snap transitions (Ctrl+Alt+Arrow in browser — OS owns Win+Arrow).
 * @returns {{ action: 'snap'|'fullscreen'|'restore'|'minimize'|'none', bounds?: object, snap?: string|null, display?: string }}
 */
export function computeSnapAction(win, direction) {
  const area = getWorkArea()
  const halfW = Math.floor(area.width / 2)
  const halfH = Math.floor(area.height / 2)
  const snap = win.snap ?? (win.display === 'fullscreen' ? 'fullscreen' : null)

  if (direction === 'left') {
    return {
      action: 'snap',
      bounds: { x: 0, y: 0, width: halfW, height: area.height },
      snap: 'left',
      display: 'mini',
    }
  }

  if (direction === 'right') {
    return {
      action: 'snap',
      bounds: { x: halfW, y: 0, width: area.width - halfW, height: area.height },
      snap: 'right',
      display: 'mini',
    }
  }

  if (direction === 'up') {
    if (snap === 'left') {
      return {
        action: 'snap',
        bounds: { x: 0, y: 0, width: halfW, height: halfH },
        snap: 'top-left',
        display: 'mini',
      }
    }
    if (snap === 'right') {
      return {
        action: 'snap',
        bounds: { x: halfW, y: 0, width: area.width - halfW, height: halfH },
        snap: 'top-right',
        display: 'mini',
      }
    }
    if (snap === 'bottom-left') {
      return {
        action: 'snap',
        bounds: { x: 0, y: 0, width: halfW, height: halfH },
        snap: 'top-left',
        display: 'mini',
      }
    }
    if (snap === 'bottom-right') {
      return {
        action: 'snap',
        bounds: { x: halfW, y: 0, width: area.width - halfW, height: halfH },
        snap: 'top-right',
        display: 'mini',
      }
    }
    if (snap === 'fullscreen') return { action: 'none' }
    return { action: 'fullscreen' }
  }

  if (direction === 'down') {
    if (snap === 'fullscreen') return { action: 'restore' }
    if (snap === 'left') {
      return {
        action: 'snap',
        bounds: { x: 0, y: halfH, width: halfW, height: area.height - halfH },
        snap: 'bottom-left',
        display: 'mini',
      }
    }
    if (snap === 'right') {
      return {
        action: 'snap',
        bounds: { x: halfW, y: halfH, width: area.width - halfW, height: area.height - halfH },
        snap: 'bottom-right',
        display: 'mini',
      }
    }
    if (snap === 'top-left' || snap === 'top-right' || snap === 'bottom-left' || snap === 'bottom-right') {
      return { action: 'restore' }
    }
    return { action: 'minimize' }
  }

  return { action: 'none' }
}

export function captureFloatingBounds(win) {
  if (win.display === 'fullscreen') return
  win.floatingBounds = {
    x: win.x,
    y: win.y,
    width: win.width,
    height: win.height,
  }
}

export function defaultFloatingBounds(win) {
  const width = win.miniWidth ?? 820
  const height = win.miniHeight ?? 520
  return { width, height, ...centerWindow(width, height) }
}

export function resolveRestoreBounds(win) {
  if (win.floatingBounds) {
    return { ...win.floatingBounds }
  }
  if (win.layoutKey) {
    return null
  }
  return defaultFloatingBounds(win)
}

export function applyFullscreen(win) {
  captureFloatingBounds(win)
  Object.assign(win, fullscreenBounds())
  win.display = 'fullscreen'
  win.snap = 'fullscreen'
}

/** Recompute snap bounds when work area size changes. */
export function getSnapBounds(snap) {
  const area = getWorkArea()
  const halfW = Math.floor(area.width / 2)
  const halfH = Math.floor(area.height / 2)

  const map = {
    left: { x: 0, y: 0, width: halfW, height: area.height },
    right: { x: halfW, y: 0, width: area.width - halfW, height: area.height },
    'top-left': { x: 0, y: 0, width: halfW, height: halfH },
    'top-right': { x: halfW, y: 0, width: area.width - halfW, height: halfH },
    'bottom-left': { x: 0, y: halfH, width: halfW, height: area.height - halfH },
    'bottom-right': { x: halfW, y: halfH, width: area.width - halfW, height: area.height - halfH },
  }

  return map[snap] ?? null
}
