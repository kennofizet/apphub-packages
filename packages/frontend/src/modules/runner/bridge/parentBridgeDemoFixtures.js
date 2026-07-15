/**
 * Parent-bridge demo payloads from launch context (host config/demo_data).
 */

/**
 * Clone plain-data demo JSON payloads. Source rows may be Vue reactive Proxies
 * (from launch context state) which structuredClone cannot handle.
 * @param {unknown} row
 * @returns {unknown}
 */
function cloneDemoPayload(row) {
  try {
    return JSON.parse(JSON.stringify(row))
  } catch {
    return row
  }
}

/**
 * @param {string} action
 * @param {Record<string, unknown>|null|undefined} fixtures
 * @returns {unknown}
 */
export function parentBridgeDemoFixture(action, fixtures) {
  const key = String(action ?? '').trim().toLowerCase()
  const map = fixtures && typeof fixtures === 'object' && !Array.isArray(fixtures)
    ? fixtures
    : {}

  if (Object.prototype.hasOwnProperty.call(map, key)) {
    const row = map[key]
    if (row && typeof row === 'object') {
      return cloneDemoPayload(row)
    }
    return row
  }

  return { _demo_fixture: true, action: key, note: 'No demo fixture for this action yet' }
}

/** @deprecated use parentBridgeDemoFixture */
export function draftParentBridgeFixture(action, fixtures) {
  return parentBridgeDemoFixture(action, fixtures)
}
