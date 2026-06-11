const KEY_PREFIX = 'apphub-bootstrap:'

function cacheKey(backendUrl) {
  return KEY_PREFIX + String(backendUrl ?? '').replace(/\/$/, '')
}

/** @returns {{ savedAt: number, isDevUser: boolean, origins: object } | null} */
export function loadBootstrapCache(backendUrl) {
  if (typeof localStorage === 'undefined' || !backendUrl) return null
  try {
    const raw = localStorage.getItem(cacheKey(backendUrl))
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (!parsed?.origins) return null
    return parsed
  } catch {
    return null
  }
}

/** @param {import('axios').AxiosResponse|{ data?: unknown }} bootstrapResponse */
export function saveBootstrapCache(backendUrl, bootstrapResponse) {
  if (typeof localStorage === 'undefined' || !backendUrl) return
  const data = bootstrapResponse?.data?.data ?? bootstrapResponse?.data ?? {}
  if (!data.origins) return
  try {
    localStorage.setItem(cacheKey(backendUrl), JSON.stringify({
      savedAt: Date.now(),
      isDevUser: data.is_dev_user === true,
      origins: data.origins,
    }))
  } catch {
    /* ignore quota */
  }
}

/** Build axios-like bootstrap response from cache entry. */
export function bootstrapResponseFromCache(entry) {
  return {
    data: {
      data: {
        is_dev_user: entry.isDevUser === true,
        origins: entry.origins,
      },
    },
  }
}
