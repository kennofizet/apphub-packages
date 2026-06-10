/**
 * @param {string|null|undefined} version
 * @returns {number[]}
 */
function parseParts(version) {
  if (!version || typeof version !== 'string') return [0, 0, 0]
  return version.trim().split('.').map((part) => {
    const n = Number.parseInt(part, 10)
    return Number.isFinite(n) ? n : 0
  })
}

/**
 * @param {string|null|undefined} next
 * @param {string|null|undefined} current
 */
export function isSemverGreaterThan(next, current) {
  const a = parseParts(next)
  const b = parseParts(current)
  const len = Math.max(a.length, b.length, 3)

  for (let i = 0; i < len; i += 1) {
    const da = a[i] ?? 0
    const db = b[i] ?? 0
    if (da > db) return true
    if (da < db) return false
  }

  return false
}
