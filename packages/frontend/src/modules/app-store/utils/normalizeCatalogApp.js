/**
 * Map apphub-backend catalog row to App Store card shape.
 * @param {unknown} row
 * @returns {object|null}
 */
export function normalizeCatalogApp(row) {
  if (!row || typeof row !== 'object') return null
  const slug = typeof row.slug === 'string' ? row.slug.trim() : ''
  if (!slug) return null

  const entryUrl = typeof row.entry_url === 'string' ? row.entry_url.trim() : ''
  const healthcheckUrl = typeof row.healthcheck_url === 'string' ? row.healthcheck_url.trim() : ''

  return {
    slug,
    version: typeof row.version === 'string' && row.version.trim() ? row.version.trim() : null,
    name: typeof row.name === 'string' && row.name.trim() ? row.name.trim() : slug,
    description: typeof row.description === 'string' ? row.description : '',
    icon: typeof row.icon === 'string' && row.icon ? row.icon : '📦',
    status: typeof row.status === 'string' ? row.status : 'active',
    runtime_type: typeof row.runtime_type === 'string' ? row.runtime_type : 'iframe',
    entry_url: entryUrl || null,
    healthcheck_url: healthcheckUrl || null,
    bundle_hash: typeof row.bundle_hash === 'string' ? row.bundle_hash : null,
    bundle_entry: typeof row.bundle_entry === 'string' ? row.bundle_entry : null,
    bundle_file_count: Number.isFinite(Number(row.bundle_file_count))
      ? Number(row.bundle_file_count)
      : null,
    installed: !!row.installed,
  }
}

/** @param {unknown} payload */
export function normalizeCatalogList(payload) {
  const rows = Array.isArray(payload) ? payload : []
  return rows.map(normalizeCatalogApp).filter(Boolean)
}
