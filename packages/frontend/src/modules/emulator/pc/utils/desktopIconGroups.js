/** Grid cell key for grouping apps at the same desktop position. */
export function cellKey(x, y) {
  return `${x},${y}`
}

/** Apps sharing the same snapped desktop cell. */
export function findAppsAtCell(apps, x, y, excludeIds = []) {
  const excluded = new Set(excludeIds)
  return (apps ?? []).filter(
    (a) =>
      a.desktopX === x &&
      a.desktopY === y &&
      !excluded.has(a.id),
  )
}

/** Desktop layout: single icons or groups (2+ apps in one cell). */
export function buildDesktopItems(apps) {
  const byCell = new Map()

  for (const app of apps ?? []) {
    if (app.desktopX == null || app.desktopY == null) continue
    const key = cellKey(app.desktopX, app.desktopY)
    if (!byCell.has(key)) byCell.set(key, [])
    byCell.get(key).push(app)
  }

  const items = []
  for (const [key, cellApps] of byCell) {
    const [x, y] = key.split(',').map(Number)
    if (cellApps.length >= 2) {
      items.push({ type: 'group', id: `group-${key}`, apps: cellApps, x, y })
    } else {
      items.push({ type: 'single', app: cellApps[0], id: cellApps[0].id, x, y })
    }
  }
  return items
}

export function occupiedCells(apps) {
  const cells = new Set()
  for (const app of apps ?? []) {
    if (app.desktopX == null || app.desktopY == null) continue
    cells.add(cellKey(app.desktopX, app.desktopY))
  }
  return cells
}

/** Move one or more apps to a grid cell (creates or joins a group). */
export function moveAppsToCell(apps, x, y) {
  for (const app of apps) {
    app.desktopX = x
    app.desktopY = y
  }
}

/** Preview apps at drop target after a drag merge. */
export function previewGroupAtCell(allApps, draggedApps, x, y) {
  const draggedIds = new Set(draggedApps.map((a) => a.id))
  const existing = findAppsAtCell(allApps, x, y, [...draggedIds])
  const merged = [...existing]
  for (const app of draggedApps) {
    if (!merged.some((a) => a.id === app.id)) merged.push(app)
  }
  return merged
}

/** Find layout item (single or group) at a grid cell. */
export function findLayoutItemAt(items, x, y) {
  return (items ?? []).find((item) => item.x === x && item.y === y) ?? null
}

export function defaultGroupLabel(labels, count) {
  return count > 1 ? `${labels.group_label} (${count})` : labels.group_label
}

export function getGroupDisplayName(settings, x, y, labels, count) {
  const key = cellKey(x, y)
  const custom = settings?.groupNames?.[key]
  if (custom) return custom
  return defaultGroupLabel(labels, count)
}

export function setGroupDisplayName(settings, x, y, name, labels, count) {
  if (!settings.groupNames) settings.groupNames = {}
  const key = cellKey(x, y)
  const trimmed = String(name ?? '').trim()
  const fallback = defaultGroupLabel(labels, count)
  if (!trimmed || trimmed === fallback) {
    delete settings.groupNames[key]
    return
  }
  settings.groupNames[key] = trimmed
}

export function migrateGroupDisplayName(settings, fromX, fromY, toX, toY) {
  const fromKey = cellKey(fromX, fromY)
  const toKey = cellKey(toX, toY)
  if (fromKey === toKey || !settings?.groupNames?.[fromKey]) return
  if (!settings.groupNames) settings.groupNames = {}
  settings.groupNames[toKey] = settings.groupNames[fromKey]
  delete settings.groupNames[fromKey]
}
