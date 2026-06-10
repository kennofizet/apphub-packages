/**
 * Extract a user-facing message from an API or client error.
 * @param {unknown} err
 * @param {string} [fallback]
 */
export function parseApiError(err, fallback = '') {
  if (err && typeof err === 'object' && err.code === 'no_api') {
    return fallback
  }

  if (err && typeof err === 'object' && err.message === 'no_api') {
    return fallback
  }

  const data = err?.response?.data
  if (data && typeof data.error === 'string' && data.error.trim()) {
    return data.error.trim()
  }

  if (data && typeof data.message === 'string' && data.message.trim()) {
    return data.message.trim()
  }

  if (typeof err?.message === 'string' && err.message.trim() && err.message !== 'no_api') {
    return err.message.trim()
  }

  return fallback
}
