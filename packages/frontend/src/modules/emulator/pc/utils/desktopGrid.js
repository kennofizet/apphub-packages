export const ICON_GRID = {
  cellW: 96,
  cellH: 96,
  paddingX: 16,
  paddingY: 16,
}

export function snapPoint(x, y, enabled = true) {
  if (!enabled) return { x: Math.round(x), y: Math.round(y) }
  const { cellW, cellH, paddingX, paddingY } = ICON_GRID
  const col = Math.max(0, Math.round((x - paddingX) / cellW))
  const row = Math.max(0, Math.round((y - paddingY) / cellH))
  return {
    x: paddingX + col * cellW,
    y: paddingY + row * cellH,
  }
}

export function clampPointToLayer(x, y, layerWidth, layerHeight) {
  return {
    x: Math.max(8, Math.min(x, Math.max(8, layerWidth - 96))),
    y: Math.max(8, Math.min(y, Math.max(8, layerHeight - 96))),
  }
}

/** Next free grid cell for a new desktop icon. */
export function nextIconGridSlot(occupied, layerWidth, layerHeight) {
  const { cellW, cellH, paddingX, paddingY } = ICON_GRID
  const cols = Math.max(1, Math.floor((layerWidth - paddingX) / cellW))
  const maxRows = Math.max(1, Math.floor((layerHeight - paddingY) / cellH))
  const taken = new Set((occupied ?? []).map((p) => `${p.x},${p.y}`))

  for (let row = 0; row < maxRows; row += 1) {
    for (let col = 0; col < cols; col += 1) {
      const x = paddingX + col * cellW
      const y = paddingY + row * cellH
      if (!taken.has(`${x},${y}`)) return { x, y }
    }
  }

  const n = occupied?.length ?? 0
  return snapPoint(paddingX + (n % cols) * cellW, paddingY + Math.floor(n / cols) * cellH, true)
}
