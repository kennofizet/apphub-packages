import { fullscreenBounds } from './windowLayout.js'

export function layoutFromSession(saved, definition) {
  const miniWidth = definition.miniWidth ?? definition.width ?? 720
  const miniHeight = definition.miniHeight ?? definition.height ?? 480
  const layoutKey = definition.layoutKey ?? null

  if (saved.display === 'fullscreen') {
    return {
      display: 'fullscreen',
      layoutKey,
      miniWidth,
      miniHeight,
      ...fullscreenBounds(),
    }
  }

  return {
    display: 'mini',
    layoutKey,
    miniWidth,
    miniHeight,
    width: saved.width ?? miniWidth,
    height: saved.height ?? miniHeight,
    x: saved.x ?? 0,
    y: saved.y ?? 0,
  }
}
