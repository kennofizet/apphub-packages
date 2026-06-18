/**
 * Version the publisher should run locally (pending upload or catalog live).
 * @param {{ version?: string|null, pending_version?: string|null }|null|undefined} app
 * @returns {string|null}
 */
export function resolvePublisherTestVersion(app) {
  if (!app || typeof app !== 'object') return null

  const pending = typeof app.pending_version === 'string' ? app.pending_version.trim() : ''
  if (pending) return pending

  const live = typeof app.version === 'string' ? app.version.trim() : ''
  return live || null
}

/**
 * Draft app whose current submission was rejected by DEV review.
 * @param {{ status?: string, version?: string|null, rejected_version?: string|null }|null|undefined} app
 * @returns {boolean}
 */
export function isRejectedDraftSubmission(app) {
  if (!app || app.status !== 'draft') return false
  if (app.current_version_review_status === 'rejected') return true

  const rejected = typeof app.rejected_version === 'string' ? app.rejected_version.trim() : ''
  const version = typeof app.version === 'string' ? app.version.trim() : ''

  return Boolean(rejected && version && rejected === version)
}

/**
 * App is waiting in the DEV review queue (new draft or pending version upgrade).
 * @param {{ status?: string, version?: string|null, pending_version?: string|null, rejected_version?: string|null, awaiting_dev_review?: boolean|null }|null|undefined} app
 * @returns {boolean}
 */
export function isAwaitingDevReview(app) {
  if (!app) return false
  if (typeof app.awaiting_dev_review === 'boolean') return app.awaiting_dev_review
  if (app.pending_version) return true
  if (app.status !== 'draft') return false

  return !isRejectedDraftSubmission(app)
}

/**
 * Publisher is running a pending upload locally while the store still serves the live version.
 * @param {{ status?: string, installedVersion?: string|null, version?: string|null, pending_version?: string|null }|null|undefined} app
 * @returns {boolean}
 */
export function isTestingPendingVersion(app) {
  if (!app || app.status === 'draft') return false

  const pending = typeof app.pending_version === 'string' ? app.pending_version.trim() : ''
  if (!pending) return false

  const installed = typeof app.installedVersion === 'string'
    ? app.installedVersion.trim()
    : (typeof app.version === 'string' ? app.version.trim() : '')

  return Boolean(installed && installed === pending)
}

function resolveInstalledVersion(app) {
  if (typeof app.installedVersion === 'string' && app.installedVersion.trim()) {
    return app.installedVersion.trim()
  }
  if (typeof app.version === 'string' && app.version.trim()) {
    return app.version.trim()
  }
  return ''
}

/**
 * Publisher still runs a rejected upload locally while the store serves the live version,
 * or a rejected initial draft submission.
 * @param {{ status?: string, installedVersion?: string|null, version?: string|null, pending_version?: string|null, rejected_version?: string|null }|null|undefined} app
 * @returns {boolean}
 */
export function isRunningRejectedVersion(app) {
  if (!app) return false
  if (isTestingPendingVersion(app)) return false

  const rejected = typeof app.rejected_version === 'string' ? app.rejected_version.trim() : ''
  if (!rejected) return false

  if (app.status === 'draft') {
    return isRejectedDraftSubmission(app)
  }

  const installed = resolveInstalledVersion(app)
  return Boolean(installed && installed === rejected)
}
