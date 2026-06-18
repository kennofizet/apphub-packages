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

  const pendingVersion = typeof row.pending_version === 'string' && row.pending_version.trim()
    ? row.pending_version.trim()
    : null

  const rejectedVersion = typeof row.rejected_version === 'string' && row.rejected_version.trim()
    ? row.rejected_version.trim()
    : null

  return {
    slug,
    version: typeof row.version === 'string' && row.version.trim() ? row.version.trim() : null,
    pending_version: pendingVersion,
    rejected_version: rejectedVersion,
    awaiting_dev_review:
      typeof row.awaiting_dev_review === 'boolean' ? row.awaiting_dev_review : null,
    current_version_review_status:
      typeof row.current_version_review_status === 'string' && row.current_version_review_status.trim()
        ? row.current_version_review_status.trim()
        : null,
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
    permissions: normalizePermissions(row.permissions),
    api_urls: normalizeApiUrls(row.api_urls, row.api_base_url),
  }
}

/** @param {unknown} raw */
function normalizeApiUrls(raw, legacy) {
  const urls = []
  if (Array.isArray(raw)) {
    for (const item of raw) {
      const value = typeof item === 'string' ? item.trim() : ''
      if (value && !urls.includes(value)) urls.push(value)
    }
  }
  const base = typeof legacy === 'string' ? legacy.trim() : ''
  if (base && !urls.includes(base)) urls.unshift(base)
  return urls
}

/** @param {unknown} raw */
function normalizePermissions(raw) {
  if (!Array.isArray(raw)) return []
  return raw
    .map((item) => (typeof item === 'string' ? item.trim() : ''))
    .filter(Boolean)
}

/** @param {unknown} payload */
export function normalizeCatalogList(payload) {
  const rows = Array.isArray(payload) ? payload : []
  return rows.map(normalizeCatalogApp).filter(Boolean)
}
