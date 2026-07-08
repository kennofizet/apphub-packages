/**
 * @param {unknown} catalog
 * @returns {{ available: boolean, actions: Array<{ name: string, scope: string }>, events: Array<{ name: string, scope: string }> }}
 */
export function normalizeParentBridgeCatalog(catalog) {
  const raw = catalog && typeof catalog === 'object' ? catalog : {}
  const actions = Array.isArray(raw.actions)
    ? raw.actions
      .filter((row) => row && typeof row.name === 'string' && typeof row.scope === 'string')
      .map((row) => ({ name: row.name.trim(), scope: row.scope.trim() }))
    : []
  const events = Array.isArray(raw.events)
    ? raw.events
      .filter((row) => row && typeof row.name === 'string' && typeof row.scope === 'string')
      .map((row) => ({ name: row.name.trim(), scope: row.scope.trim() }))
    : []

  return {
    available: raw.available === true || actions.length > 0 || events.length > 0,
    actions,
    events,
  }
}

/**
 * @param {{ actions?: Array<{ name: string, scope: string }> }} catalog
 * @param {string} action
 */
export function findParentBridgeAction(catalog, action) {
  const name = String(action ?? '').trim()
  if (!name) return null
  return catalog?.actions?.find((row) => row.name === name) ?? null
}

/**
 * @param {{ events?: Array<{ name: string, scope: string }> }} catalog
 * @param {string} eventName
 */
export function findParentBridgeEvent(catalog, eventName) {
  const name = String(eventName ?? '').trim()
  if (!name) return null
  return catalog?.events?.find((row) => row.name === name) ?? null
}
