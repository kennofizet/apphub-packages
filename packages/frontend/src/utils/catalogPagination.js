/** packages-core ValidatorRequestMiddleware rejects per_page > 50. */
export const CATALOG_MAX_PER_PAGE = 50

export const CATALOG_DEFAULT_PER_PAGE = 24

/**
 * @param {number | string | null | undefined} value
 * @param {number} [fallback]
 */
export function clampPerPage(value, fallback = CATALOG_DEFAULT_PER_PAGE) {
  const n = Number(value)
  const base = Number.isFinite(n) && n > 0 ? Math.floor(n) : fallback
  return Math.min(CATALOG_MAX_PER_PAGE, Math.max(1, base))
}
